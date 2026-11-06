import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:intl/intl.dart';

import '../api.dart';
import 'home.dart' show fmtLocal;

/// Remote / field duty request, punch correction request, and the list of my requests.
class RequestsScreen extends StatefulWidget {
  const RequestsScreen({super.key, this.initialTab = 0});
  final int initialTab;

  @override
  State<RequestsScreen> createState() => _RequestsScreenState();
}

class _RequestsScreenState extends State<RequestsScreen> {
  static const _types = {'wfh': 'Work from home', 'field_duty': 'Field duty', 'client_site': 'Client-site work', 'temporary_remote': 'Temporary remote duty'};
  final _fmt = DateFormat('yyyy-MM-dd');

  // remote form
  String _type = 'wfh';
  DateTimeRange _range = DateTimeRange(start: DateTime.now(), end: DateTime.now());
  final _reason = TextEditingController();
  bool _useLocation = true;

  // correction form
  String _cType = 'OUT';
  DateTime _cDate = DateTime.now().subtract(const Duration(days: 1));
  TimeOfDay _cTime = const TimeOfDay(hour: 18, minute: 30);
  final _cReason = TextEditingController();

  bool _busy = false;
  Map<String, dynamic>? _mine;

  @override
  void initState() {
    super.initState();
    _loadMine();
  }

  @override
  void dispose() {
    _reason.dispose();
    _cReason.dispose();
    super.dispose();
  }

  Future<void> _loadMine() async {
    try {
      final m = await api.requests();
      if (mounted) setState(() => _mine = m);
    } on ApiException catch (_) {}
  }

  void _toast(String m) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(m)));

  Future<void> _sendRemote() async {
    if (_reason.text.trim().isEmpty) return _toast('Enter a reason');
    setState(() => _busy = true);
    try {
      final body = <String, dynamic>{'type': _type, 'from': _fmt.format(_range.start), 'to': _fmt.format(_range.end), 'reason': _reason.text.trim()};
      if (_useLocation) {
        final pos = await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 15)));
        body.addAll({'lat': pos.latitude, 'lng': pos.longitude, 'location_label': 'My current location'});
      }
      await api.requestRemote(body);
      _reason.clear();
      _toast('Request sent to your administrator');
      _loadMine();
    } on ApiException catch (e) {
      _toast(e.message);
    } catch (_) {
      _toast('Could not get your location — untick “use my current location” or try again outdoors.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _sendCorrection() async {
    if (_cReason.text.trim().isEmpty) return _toast('Enter a reason');
    setState(() => _busy = true);
    try {
      final t = '${_cTime.hour.toString().padLeft(2, '0')}:${_cTime.minute.toString().padLeft(2, '0')}';
      await api.requestCorrection({'type': _cType, 'date': _fmt.format(_cDate), 'time': t, 'reason': _cReason.text.trim()});
      _cReason.clear();
      _toast('Correction sent for approval');
      _loadMine();
    } on ApiException catch (e) {
      _toast(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _status(String s) {
    final c = {'approved': Colors.green, 'pending': Colors.orange, 'rejected': Colors.red}[s] ?? Colors.grey;
    return Chip(label: Text(s), backgroundColor: c.shade50, side: BorderSide(color: c.shade200), visualDensity: VisualDensity.compact);
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      initialIndex: widget.initialTab,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Requests'),
          bottom: const TabBar(tabs: [Tab(text: 'Remote'), Tab(text: 'Correction'), Tab(text: 'Mine')]),
        ),
        body: TabBarView(children: [
          ListView(padding: const EdgeInsets.all(20), children: [
            DropdownButtonFormField<String>(
              value: _type,
              decoration: const InputDecoration(labelText: 'Type'),
              items: _types.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
              onChanged: (v) => setState(() => _type = v ?? 'wfh'),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              icon: const Icon(Icons.date_range),
              label: Text('${DateFormat('d MMM').format(_range.start)} → ${DateFormat('d MMM yyyy').format(_range.end)}'),
              onPressed: () async {
                final r = await showDateRangePicker(context: context, firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 90)), initialDateRange: _range);
                if (r != null) setState(() => _range = r);
              },
            ),
            const SizedBox(height: 12),
            TextField(controller: _reason, decoration: const InputDecoration(labelText: 'Reason'), maxLength: 255),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _useLocation,
              onChanged: (v) => setState(() => _useLocation = v),
              title: const Text('Use my current location as the approved place'),
              subtitle: const Text('For work from home. Turn off for field duty — your administrator decides.'),
            ),
            const SizedBox(height: 8),
            FilledButton(onPressed: _busy ? null : _sendRemote, child: const Text('Send request')),
          ]),
          ListView(padding: const EdgeInsets.all(20), children: [
            const Text('Forgot to punch? Ask your administrator to add it. Approved corrections are marked as adjusted.', style: TextStyle(color: Colors.black54)),
            const SizedBox(height: 12),
            SegmentedButton<String>(
              segments: const [ButtonSegment(value: 'IN', label: Text('IN')), ButtonSegment(value: 'OUT', label: Text('OUT'))],
              selected: {_cType},
              onSelectionChanged: (s) => setState(() => _cType = s.first),
            ),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(
                child: OutlinedButton.icon(
                  icon: const Icon(Icons.event),
                  label: Text(DateFormat('d MMM yyyy').format(_cDate)),
                  onPressed: () async {
                    final d = await showDatePicker(context: context, firstDate: DateTime.now().subtract(const Duration(days: 30)), lastDate: DateTime.now(), initialDate: _cDate);
                    if (d != null) setState(() => _cDate = d);
                  },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  icon: const Icon(Icons.schedule),
                  label: Text(_cTime.format(context)),
                  onPressed: () async {
                    final t = await showTimePicker(context: context, initialTime: _cTime);
                    if (t != null) setState(() => _cTime = t);
                  },
                ),
              ),
            ]),
            const SizedBox(height: 12),
            TextField(controller: _cReason, decoration: const InputDecoration(labelText: 'Reason'), maxLength: 255),
            FilledButton(onPressed: _busy ? null : _sendCorrection, child: const Text('Send correction')),
          ]),
          RefreshIndicator(
            onRefresh: _loadMine,
            child: ListView(padding: const EdgeInsets.all(12), children: [
              if (_mine == null) const Padding(padding: EdgeInsets.all(40), child: Center(child: CircularProgressIndicator())),
              ...((_mine?['remote'] as List?) ?? const []).map((p) => Card(
                    child: ListTile(
                      leading: const Icon(Icons.home_work_outlined),
                      title: Text('${p['type_label']} · ${p['from']} → ${p['to']}'),
                      subtitle: Text('${p['reason']}'),
                      trailing: _status('${p['status']}'),
                    ),
                  )),
              ...((_mine?['corrections'] as List?) ?? const []).map((c) => Card(
                    child: ListTile(
                      leading: const Icon(Icons.edit_calendar_outlined),
                      title: Text('${c['type']} · ${fmtLocal(c['at'] as String?, 'd MMM, HH:mm')}'),
                      subtitle: Text('${c['reason']}'),
                      trailing: _status('${c['status']}'),
                    ),
                  )),
              if (_mine != null && ((_mine!['remote'] as List).isEmpty && (_mine!['corrections'] as List).isEmpty))
                const Padding(padding: EdgeInsets.all(40), child: Center(child: Text('No requests in the last 60 days.'))),
            ]),
          ),
        ]),
      ),
    );
  }
}

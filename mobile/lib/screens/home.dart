import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api.dart';
import 'history.dart';
import 'remote.dart';
import 'requests.dart';
import 'scan.dart';

String fmtLocal(String? iso, [String pattern = 'd MMM, HH:mm']) {
  final t = iso == null ? null : DateTime.tryParse(iso);
  return t == null ? '—' : DateFormat(pattern).format(t.toLocal());
}

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Map<String, dynamic>? _me;
  String? _error;

  @override
  void initState() {
    super.initState();
    _refresh();
    WidgetsBinding.instance.addPostFrameCallback((_) => _showNotice());
  }

  Future<void> _showNotice() async {
    final n = api.pendingNotice;
    if (n == null || !mounted) return;
    api.pendingNotice = null;
    await showDialog<void>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Location notice'),
        content: Text(n),
        actions: [FilledButton(onPressed: () => Navigator.pop(c), child: const Text('I understand'))],
      ),
    );
  }

  Future<void> _refresh() async {
    try {
      final me = await api.me();
      if (mounted) {
        setState(() {
          _me = me;
          _error = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  Future<void> _scan() async {
    final type = (_me?['next_type'] ?? 'IN').toString();
    await Navigator.push(context, MaterialPageRoute(builder: (_) => ScanScreen(type: type)));
    _refresh();
  }

  void _deviceSheet() {
    final d = (_me?['device'] as Map?) ?? {};
    final notice = (_me?['policy']?['location_notice'] ?? '').toString();
    showModalBottomSheet<void>(
      context: context,
      builder: (_) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('This device', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            _row('Model', d['model']),
            _row('Install reference', d['install_ref']),
            _row('Status', d['status']),
            _row('Registered', fmtLocal(d['registered_at'] as String?, 'd MMM yyyy')),
            if (notice.isNotEmpty) ...[const SizedBox(height: 12), Text(notice, style: const TextStyle(color: Colors.black54))],
          ]),
        ),
      ),
    );
  }

  Widget _row(String k, Object? v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(children: [Expanded(child: Text(k, style: const TextStyle(color: Colors.black54))), Text('${v ?? '—'}')]),
      );

  Future<void> _signOut() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Sign out of this phone?'),
        content: const Text('You will need a new activation code to punch from this phone again.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Sign out')),
        ],
      ),
    );
    if (ok == true) await api.signOut();
  }

  @override
  Widget build(BuildContext context) {
    final me = _me;
    final emp = (me?['employee'] as Map?) ?? {};
    final dev = (me?['device'] as Map?) ?? {};
    final last = me?['last_event'] as Map?;
    final status = (dev['status'] ?? '').toString();
    final next = (me?['next_type'] ?? 'IN').toString();
    final hour = DateTime.now().hour;

    return Scaffold(
      appBar: AppBar(
        title: const Text('QRONEX'),
        actions: [
          PopupMenuButton<String>(
            onSelected: (v) async {
              if (v == 'device') return _deviceSheet();
              if (v == 'signout') return _signOut();
              await Navigator.push(context, MaterialPageRoute(builder: (_) => RequestsScreen(initialTab: v == 'correction' ? 1 : (v == 'mine' ? 2 : 0))));
              _refresh();
            },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'remote', child: Text('Request remote / field duty')),
              PopupMenuItem(value: 'correction', child: Text('Request punch correction')),
              PopupMenuItem(value: 'mine', child: Text('My requests')),
              PopupMenuItem(value: 'device', child: Text('This device')),
              PopupMenuItem(value: 'signout', child: Text('Sign out of this phone')),
            ],
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (_error != null) _banner(_error!, Colors.red),
            if (me == null && _error == null) const Padding(padding: EdgeInsets.all(40), child: Center(child: CircularProgressIndicator())),
            if (me != null) ...[
              Text('Good ${hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening'},', style: const TextStyle(color: Colors.black54)),
              Text('${emp['name'] ?? ''}', style: const TextStyle(fontSize: 26, fontWeight: FontWeight.bold)),
              Text('${me['company']?['name'] ?? ''} · ${emp['office'] ?? ''}', style: const TextStyle(color: Colors.black54)),
              const SizedBox(height: 16),
              if (status != 'approved')
                _banner(
                  status == 'pending'
                      ? 'This phone is waiting for administrator approval. Punches will be rejected until it is approved.'
                      : 'This phone is $status. Contact your administrator.',
                  Colors.orange,
                  action: TextButton(onPressed: _refresh, child: const Text('Refresh')),
                ),
              Card(
                child: ListTile(
                  title: const Text('Last punch'),
                  trailing: Text(last == null ? '—' : '${last['type']} · ${fmtLocal(last['at'] as String?)}', style: const TextStyle(fontWeight: FontWeight.bold)),
                  subtitle: last == null ? null : Text('${last['office'] ?? ''}${last['gate'] != null ? ' · ${last['gate']}' : ''}'),
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                height: 140,
                child: FilledButton.icon(
                  onPressed: status == 'approved' ? _scan : null,
                  icon: const Icon(Icons.qr_code_scanner, size: 40),
                  label: Text('Scan QR to punch $next', style: const TextStyle(fontSize: 20)),
                  style: FilledButton.styleFrom(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
                ),
              ),
              if (me['remote_permission'] is Map) ...[
                const SizedBox(height: 12),
                Card(
                  color: Theme.of(context).colorScheme.secondaryContainer,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      Text('${me['remote_permission']['type_label']} approved · ${me['remote_permission']['from']} → ${me['remote_permission']['to']}',
                          style: const TextStyle(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 4),
                      Text(me['remote_permission']['location_waived'] == true
                          ? 'Location check waived by your administrator'
                          : 'Must be within ${me['remote_permission']['location']?['radius_m']} m of ${me['remote_permission']['location']?['label'] ?? 'the approved location'}',
                          style: const TextStyle(color: Colors.black54)),
                      const SizedBox(height: 10),
                      FilledButton.tonalIcon(
                        onPressed: status == 'approved'
                            ? () async {
                                await Navigator.push(context, MaterialPageRoute(builder: (_) => RemoteScreen(type: next)));
                                _refresh();
                              }
                            : null,
                        icon: const Icon(Icons.home_work_outlined),
                        label: Text('Remote ${next == 'IN' ? 'check-in' : 'checkout'}'),
                      ),
                    ]),
                  ),
                ),
              ],
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const HistoryScreen())),
                icon: const Icon(Icons.history),
                label: const Text('My attendance'),
                style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _banner(String text, MaterialColor c, {Widget? action}) => Container(
        margin: const EdgeInsets.only(bottom: 16),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: c.shade50, borderRadius: BorderRadius.circular(8), border: Border.all(color: c.shade200)),
        child: Row(children: [Expanded(child: Text(text, style: TextStyle(color: c.shade900))), if (action != null) action]),
      );
}

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../api.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  late Future<List<dynamic>> _events = api.history();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My attendance')),
      body: FutureBuilder<List<dynamic>>(
        future: _events,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) return const Center(child: CircularProgressIndicator());
          if (snap.hasError) {
            return Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Text('${snap.error}'),
                TextButton(onPressed: () => setState(() => _events = api.history()), child: const Text('Retry')),
              ]),
            );
          }
          final events = (snap.data ?? const []).whereType<Map>().toList();
          if (events.isEmpty) return const Center(child: Text('No punches in the last 30 days.'));

          // Group by local calendar date, newest first.
          final groups = <String, List<Map>>{};
          for (final e in events) {
            final t = DateTime.tryParse('${e['at']}')?.toLocal();
            if (t == null) continue;
            groups.putIfAbsent(DateFormat('EEE, d MMM yyyy').format(t), () => []).add({...e, '_t': t});
          }
          final days = groups.entries.toList()
            ..sort((a, b) => (b.value.first['_t'] as DateTime).compareTo(a.value.first['_t'] as DateTime));

          return RefreshIndicator(
            onRefresh: () async {
              final f = api.history();
              setState(() => _events = f);
              await f;
            },
            child: ListView(children: [
              for (final d in days) ...[
                Padding(padding: const EdgeInsets.fromLTRB(16, 16, 16, 4), child: Text(d.key, style: const TextStyle(fontWeight: FontWeight.bold))),
                for (final e in d.value..sort((a, b) => (a['_t'] as DateTime).compareTo(b['_t'] as DateTime)))
                  ListTile(
                    leading: CircleAvatar(
                      backgroundColor: e['type'] == 'IN' ? Colors.green.shade100 : Colors.blue.shade100,
                      child: Text('${e['type']}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                    ),
                    title: Text(DateFormat('HH:mm:ss').format(e['_t'] as DateTime)),
                    subtitle: Text('${e['office'] ?? e['mode'] ?? ''}${e['gate'] != null ? ' · ${e['gate']}' : ''}${e['adjusted'] == true ? ' · adjusted' : ''}'),
                    trailing: Text('${e['ref'] ?? ''}', style: const TextStyle(fontSize: 11, color: Colors.black45)),
                  ),
              ],
            ]),
          );
        },
      ),
    );
  }
}

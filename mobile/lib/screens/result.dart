import 'package:flutter/material.dart';

import 'home.dart' show fmtLocal;

class ResultScreen extends StatelessWidget {
  const ResultScreen({super.key, required this.result});
  final Map<String, dynamic> result;

  static const _icons = {
    'ok': (Icons.check_circle, Colors.green),
    'bad': (Icons.cancel, Colors.red),
    'na': (Icons.info_outline, Colors.blueGrey),
    'skip': (Icons.remove_circle_outline, Colors.grey),
  };

  @override
  Widget build(BuildContext context) {
    final ok = result['ok'] == true;
    final ev = (result['event'] as Map?) ?? {};
    final checks = (result['checks'] as List?) ?? const [];
    final color = ok ? Colors.green : Colors.red;

    return Scaffold(
      appBar: AppBar(title: Text(ok ? 'Recorded' : 'Not recorded'), automaticallyImplyLeading: false),
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.all(20), children: [
          Icon(ok ? Icons.check_circle : Icons.cancel, color: color, size: 96),
          const SizedBox(height: 8),
          if (ok) ...[
            Text('${ev['type']} recorded', textAlign: TextAlign.center, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
            Text(fmtLocal(ev['at'] as String?, 'HH:mm:ss'), textAlign: TextAlign.center, style: const TextStyle(fontSize: 34, fontWeight: FontWeight.bold)),
            Text('${ev['office'] ?? ''}${ev['gate'] != null ? ' · ${ev['gate']}' : ''}', textAlign: TextAlign.center),
            if (ev['distance_m'] != null) Text('${ev['distance_m']} m from office centre', textAlign: TextAlign.center, style: const TextStyle(color: Colors.black54)),
            Text('Ref ${ev['ref'] ?? ''}', textAlign: TextAlign.center, style: const TextStyle(fontFamily: 'monospace', color: Colors.black54)),
          ] else ...[
            const Text('Attendance not recorded', textAlign: TextAlign.center, style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Text('${result['reason'] ?? ''}', textAlign: TextAlign.center, style: const TextStyle(fontSize: 16)),
            if (result['ref'] != null) Text('Ref ${result['ref']}', textAlign: TextAlign.center, style: const TextStyle(fontFamily: 'monospace', color: Colors.black54)),
          ],
          const SizedBox(height: 16),
          if (checks.isNotEmpty)
            Card(
              child: ExpansionTile(
                title: const Text('Server checks'),
                initiallyExpanded: !ok,
                children: [
                  for (final c in checks.whereType<Map>())
                    ListTile(
                      dense: true,
                      leading: Icon(_icons[c['status']]?.$1 ?? Icons.circle_outlined, color: _icons[c['status']]?.$2),
                      title: Text('${c['label'] ?? ''}'),
                      subtitle: Text('${c['detail'] ?? ''}'),
                    ),
                ],
              ),
            ),
          const SizedBox(height: 16),
          FilledButton(onPressed: () => Navigator.pop(context), child: const Text('Done')),
        ]),
      ),
    );
  }
}

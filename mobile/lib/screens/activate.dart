import 'dart:io';
import 'dart:math';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/material.dart';

import '../api.dart';

class ActivateScreen extends StatefulWidget {
  const ActivateScreen({super.key});

  @override
  State<ActivateScreen> createState() => _ActivateScreenState();
}

class _ActivateScreenState extends State<ActivateScreen> {
  final _form = GlobalKey<FormState>();
  // Release builds pin the server: flutter build apk --dart-define=QRONEX_SERVER=https://qronex.app — the field is then hidden.
  static const _fixedServer = String.fromEnvironment('QRONEX_SERVER');
  final _server = TextEditingController(text: _fixedServer.isNotEmpty ? _fixedServer : (api.baseUrl ?? 'http://192.168.1.10:8000'));
  final _company = TextEditingController();
  final _employee = TextEditingController();
  final _code = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _error = api.signOutMessage;
  }

  @override
  void dispose() {
    for (final c in [_server, _company, _employee, _code]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<String> _installRef() async {
    var ref = await api.installRef();
    if (ref.isEmpty) {
      final r = Random.secure();
      ref = List.generate(24, (_) => r.nextInt(16).toRadixString(16)).join();
      await api.saveInstallRef(ref);
    }
    return ref;
  }

  Future<Map<String, String>> _device() async {
    final info = DeviceInfoPlugin();
    if (Platform.isIOS) {
      final i = await info.iosInfo;
      return {'platform': 'ios', 'model': '${i.model} (${i.utsname.machine})', 'os_version': 'iOS ${i.systemVersion}'};
    }
    final a = await info.androidInfo;
    return {'platform': 'android', 'model': '${a.manufacturer} ${a.model}', 'os_version': 'Android ${a.version.release}'};
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      // On success api.signedIn flips and the app swaps to HomeScreen (which shows the location notice).
      await api.activate(_server.text, {
        'company': _company.text.trim().toLowerCase(),
        'employee_code': _employee.text.trim(),
        'activation_code': _code.text.trim(),
        'install_ref': await _installRef(),
        ...await _device(),
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String? _required(String? v) => (v == null || v.trim().isEmpty) ? 'Required' : null;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Activate QRONEX')),
      body: SafeArea(
        child: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              const Text('Enter the details from your activation SMS or email.', style: TextStyle(fontSize: 15)),
              const SizedBox(height: 16),
              if (_error != null)
                Container(
                  padding: const EdgeInsets.all(12),
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(8)),
                  child: Text(_error!, style: TextStyle(color: Colors.red.shade800)),
                ),
              if (_fixedServer.isEmpty) ...[
              TextFormField(
                controller: _server,
                keyboardType: TextInputType.url,
                decoration: const InputDecoration(
                  labelText: 'Server address',
                  helperText: 'During testing: http://<PC LAN IP>:8000 — the phone must reach that PC',
                ),
                validator: (v) => (v != null && Uri.tryParse(v.trim())?.hasScheme == true) ? null : 'Enter a full URL, e.g. http://192.168.1.10:8000',
              ),
              const SizedBox(height: 14),
              ],
              TextFormField(controller: _company, decoration: const InputDecoration(labelText: 'Company code', hintText: 'e.g. stormfinancial'), validator: _required),
              const SizedBox(height: 14),
              TextFormField(controller: _employee, decoration: const InputDecoration(labelText: 'Employee code', hintText: 'e.g. EMP0007'), validator: _required),
              const SizedBox(height: 14),
              TextFormField(
                controller: _code,
                textCapitalization: TextCapitalization.characters,
                decoration: const InputDecoration(labelText: 'Activation code'),
                validator: _required,
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _busy ? null : _submit,
                child: _busy ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Activate this phone'),
              ),
              const SizedBox(height: 12),
              const Text('This phone is registered to you. Your administrator may need to approve it before you can punch.', style: TextStyle(color: Colors.black54, fontSize: 13)),
            ],
          ),
        ),
      ),
    );
  }
}

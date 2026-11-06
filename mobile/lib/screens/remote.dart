import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';

import '../api.dart';
import 'result.dart';

/// Remote check-in / checkout under an approved permission: no QR, server checks date, permission and location.
class RemoteScreen extends StatefulWidget {
  const RemoteScreen({super.key, required this.type});
  final String type;

  @override
  State<RemoteScreen> createState() => _RemoteScreenState();
}

class _RemoteScreenState extends State<RemoteScreen> {
  String _status = 'Locating…';
  String? _error;

  @override
  void initState() {
    super.initState();
    _go();
  }

  Future<void> _go() async {
    setState(() {
      _status = 'Locating…';
      _error = null;
    });
    try {
      if (!await Geolocator.isLocationServiceEnabled()) throw 'Turn on location to check in remotely.';
      var perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
      if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) throw 'QRONEX needs location permission for remote check-in.';
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.best, timeLimit: Duration(seconds: 20)),
      );
      if (!mounted) return;
      setState(() => _status = 'Verifying…');
      final res = await api.remoteScan({'lat': pos.latitude, 'lng': pos.longitude, 'accuracy': pos.accuracy, 'mocked': pos.isMocked, 'type': widget.type});
      if (!mounted) return;
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => ResultScreen(result: res)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      if (mounted) setState(() => _error = e is String ? e : 'Could not get your location. Move near a window or outdoors and try again.');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Remote ${widget.type == 'IN' ? 'check-in' : 'checkout'}')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: _error == null
              ? Column(mainAxisSize: MainAxisSize.min, children: [const CircularProgressIndicator(), const SizedBox(height: 16), Text(_status, style: const TextStyle(fontSize: 18))])
              : Column(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.location_off, size: 48, color: Colors.orange),
                  const SizedBox(height: 12),
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton(onPressed: _go, child: const Text('Try again')),
                  TextButton(onPressed: () => Geolocator.openAppSettings(), child: const Text('Open settings')),
                ]),
        ),
      ),
    );
  }
}

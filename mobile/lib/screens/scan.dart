import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../api.dart';
import 'result.dart';

class ScanScreen extends StatefulWidget {
  const ScanScreen({super.key, required this.type});
  final String type; // IN | OUT

  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends State<ScanScreen> {
  final _controller = MobileScannerController(formats: const [BarcodeFormat.qrCode]);
  bool _handled = false;
  String? _status; // overlay text while locating / sending
  String? _error;
  bool _needsSettings = false;

  // GPS starts when the screen opens: the QR is valid only ~8 s, a cold GPS fix can take longer.
  StreamSubscription<Position>? _gps;
  Position? _last;

  @override
  void initState() {
    super.initState();
    _startGps();
  }

  Future<void> _startGps() async {
    try {
      await _ensurePermission();
      _gps = Geolocator.getPositionStream(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.best, distanceFilter: 0),
      ).listen((p) => _last = p, onError: (_) {});
    } catch (_) {
      // Reported when the user scans (via _position()).
    }
  }

  @override
  void dispose() {
    _gps?.cancel();
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_handled) return;
    String? qr;
    for (final b in capture.barcodes) {
      if (b.rawValue?.startsWith('QRX1.') ?? false) {
        qr = b.rawValue;
        break;
      }
    }
    if (qr == null) return;
    _handled = true;
    await _controller.stop();
    setState(() => _status = 'Locating…');

    try {
      final pos = await _position();
      if (!mounted) return;
      setState(() => _status = 'Verifying…');
      final res = await api.scan({
        'qr': qr,
        'lat': pos.latitude,
        'lng': pos.longitude,
        'accuracy': pos.accuracy,
        'mocked': pos.isMocked,
        'type': widget.type,
      });
      if (!mounted) return;
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => ResultScreen(result: res)));
    } on _LocationProblem catch (e) {
      _fail(e.message, settings: e.settings);
    } on ApiException catch (e) {
      _fail(e.message);
    } catch (_) {
      _fail('Could not get your location in time. Move near a window or outdoors and try again.');
    }
  }

  void _fail(String msg, {bool settings = false}) {
    if (!mounted) return;
    setState(() {
      _status = null;
      _error = msg;
      _needsSettings = settings;
    });
  }

  /// Fresh fix from the running stream if we have one (≤ 20 s old), else ask once.
  Future<Position> _position() async {
    final last = _last;
    if (last != null && DateTime.now().difference(last.timestamp).inSeconds <= 20) return last;
    await _ensurePermission();
    return Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.best, timeLimit: Duration(seconds: 12)),
    );
  }

  Future<void> _ensurePermission() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw _LocationProblem('Location is turned off. Turn on location to punch attendance.', settings: true);
    }
    var perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
    if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) {
      throw _LocationProblem('QRONEX needs location permission to confirm you are at the office.', settings: true);
    }
  }

  Future<void> _retry() async {
    setState(() {
      _error = null;
      _handled = false;
    });
    if (_gps == null) _startGps();
    await _controller.start();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Punch ${widget.type}')),
      body: Stack(fit: StackFit.expand, children: [
        MobileScanner(controller: _controller, onDetect: _onDetect),
        Center(
          child: Container(
            width: 250,
            height: 250,
            decoration: BoxDecoration(border: Border.all(color: Colors.white, width: 3), borderRadius: BorderRadius.circular(16)),
          ),
        ),
        const Positioned(
          left: 0,
          right: 0,
          bottom: 40,
          child: Text('Point the camera at the entrance QR display', textAlign: TextAlign.center, style: TextStyle(color: Colors.white, fontSize: 16)),
        ),
        if (_status != null)
          ColoredBox(
            color: Colors.black54,
            child: Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const CircularProgressIndicator(color: Colors.white),
                const SizedBox(height: 16),
                Text(_status!, style: const TextStyle(color: Colors.white, fontSize: 18)),
              ]),
            ),
          ),
        if (_error != null)
          ColoredBox(
            color: Colors.black87,
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.location_off, color: Colors.orangeAccent, size: 48),
                  const SizedBox(height: 12),
                  Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 16)),
                  const SizedBox(height: 20),
                  if (_needsSettings)
                    FilledButton(onPressed: () => Geolocator.openAppSettings(), child: const Text('Open settings')),
                  const SizedBox(height: 8),
                  OutlinedButton(
                    onPressed: _retry,
                    style: OutlinedButton.styleFrom(foregroundColor: Colors.white, minimumSize: const Size.fromHeight(48)),
                    child: const Text('Try again'),
                  ),
                ]),
              ),
            ),
          ),
      ]),
    );
  }
}

class _LocationProblem implements Exception {
  _LocationProblem(this.message, {this.settings = false});
  final String message;
  final bool settings;
}

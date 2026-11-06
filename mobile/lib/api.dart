import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

class ApiException implements Exception {
  ApiException(this.message, this.status, [this.body]);
  final String message;
  final int status;
  final Map<String, dynamic>? body;
  @override
  String toString() => message;
}

/// Thin client for the QRONEX mobile API (`{server}/api/v1`).
class Api {
  static const _storage = FlutterSecureStorage();
  static const _timeout = Duration(seconds: 15);

  String? baseUrl;
  String? _token;

  /// true = activated; flips to false on sign-out or a 401.
  final ValueNotifier<bool> signedIn = ValueNotifier(false);

  /// Shown on the activation screen after a forced sign-out.
  String? signOutMessage;

  /// Location notice from activation; HomeScreen shows it once.
  String? pendingNotice;

  Future<void> load() async {
    baseUrl = await _storage.read(key: 'base_url');
    _token = await _storage.read(key: 'token');
    signedIn.value = _token != null && baseUrl != null;
  }

  Future<String> installRef() async {
    return (await _storage.read(key: 'install_ref')) ?? '';
  }

  Future<void> saveInstallRef(String ref) => _storage.write(key: 'install_ref', value: ref);

  Future<Map<String, dynamic>> activate(String server, Map<String, dynamic> body) async {
    baseUrl = server.trim().replaceAll(RegExp(r'/+$'), '');
    final res = await _send('POST', '/activate', body: body, auth: false);
    _token = res['token'] as String;
    await _storage.write(key: 'base_url', value: baseUrl);
    await _storage.write(key: 'token', value: _token);
    signOutMessage = null;
    final notice = (res['policy']?['location_notice'] ?? '').toString();
    pendingNotice = notice.isEmpty ? null : notice;
    signedIn.value = true;
    return res;
  }

  Future<Map<String, dynamic>> me() => _send('GET', '/me');

  /// Returns the body for both accepted (200) and rejected (422) scans; check `ok`.
  Future<Map<String, dynamic>> scan(Map<String, dynamic> body) async {
    try {
      return await _send('POST', '/scan', body: body);
    } on ApiException catch (e) {
      if (e.status == 422 && e.body != null && e.body!['ok'] == false) return e.body!;
      rethrow;
    }
  }

  /// Remote check-in (approved WFH / field permission today). Like [scan], returns the 422 body.
  Future<Map<String, dynamic>> remoteScan(Map<String, dynamic> body) async {
    try {
      return await _send('POST', '/remote-scan', body: body);
    } on ApiException catch (e) {
      if (e.status == 422 && e.body != null && e.body!['ok'] == false) return e.body!;
      rethrow;
    }
  }

  Future<Map<String, dynamic>> requestRemote(Map<String, dynamic> body) => _send('POST', '/remote-requests', body: body);

  Future<Map<String, dynamic>> requestCorrection(Map<String, dynamic> body) => _send('POST', '/corrections', body: body);

  Future<Map<String, dynamic>> requests() => _send('GET', '/requests');

  Future<List<dynamic>> history({int days = 30}) async {
    final res = await _send('GET', '/history?days=$days');
    return (res['events'] as List?) ?? const [];
  }

  /// Local sign-out. Keeps install_ref so the server still recognises this installation.
  Future<void> signOut([String? message]) async {
    _token = null;
    await _storage.delete(key: 'token');
    signOutMessage = message;
    signedIn.value = false;
  }

  Future<Map<String, dynamic>> _send(String method, String path, {Map<String, dynamic>? body, bool auth = true}) async {
    if (baseUrl == null) throw ApiException('Server URL not set', 0);
    final uri = Uri.parse('$baseUrl/api/v1$path');
    final headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (auth && _token != null) 'Authorization': 'Bearer $_token',
    };

    http.Response r;
    try {
      r = await (method == 'GET'
              ? http.get(uri, headers: headers)
              : http.post(uri, headers: headers, body: jsonEncode(body ?? const {})))
          .timeout(_timeout);
    } catch (_) {
      throw ApiException('Cannot reach the QRONEX server. Check Wi-Fi/data and the server address.', 0);
    }

    Map<String, dynamic>? json;
    try {
      final d = jsonDecode(r.body);
      if (d is Map<String, dynamic>) json = d;
    } catch (_) {}

    if (r.statusCode >= 200 && r.statusCode < 300) return json ?? {};

    if (r.statusCode == 401 && auth) {
      await signOut('This phone was signed out by your administrator.');
      throw ApiException('This phone was signed out by your administrator.', 401, json);
    }
    if (r.statusCode == 429) {
      throw ApiException('Slow down, try again in a minute.', 429, json);
    }
    final errs = json?['errors'];
    final first = errs is Map && errs.isNotEmpty ? (errs.values.first is List ? (errs.values.first as List).first : errs.values.first) : null;
    final msg = (first ?? json?['message'] ?? json?['reason'] ?? 'Server error (${r.statusCode})').toString();
    throw ApiException(msg, r.statusCode, json);
  }
}

final api = Api();

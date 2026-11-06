// Exists so `flutter create .` doesn't generate the default counter test (it references MyApp, which this app doesn't have).
import 'package:flutter_test/flutter_test.dart';
import 'package:qronex_app/screens/home.dart';

void main() {
  test('fmtLocal formats ISO UTC and tolerates null', () {
    expect(fmtLocal(null), '—');
    expect(fmtLocal('not-a-date'), '—');
    expect(fmtLocal('2026-11-05T04:30:00Z', 'yyyy'), '2026');
  });
}

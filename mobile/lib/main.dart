import 'package:flutter/material.dart';

import 'api.dart';
import 'screens/activate.dart';
import 'screens/home.dart';

const navy = Color(0xFF0E2747);
const teal = Color(0xFF0A8278);

final navigatorKey = GlobalKey<NavigatorState>();

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await api.load();
  // A forced sign-out (401) can happen on any pushed screen: drop back to the root.
  api.signedIn.addListener(() {
    if (!api.signedIn.value) navigatorKey.currentState?.popUntil((r) => r.isFirst);
  });
  runApp(const QronexApp());
}

class QronexApp extends StatelessWidget {
  const QronexApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'QRONEX',
      navigatorKey: navigatorKey,
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: teal, primary: teal, secondary: navy),
        appBarTheme: const AppBarTheme(backgroundColor: navy, foregroundColor: Colors.white),
        filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(backgroundColor: teal, minimumSize: const Size.fromHeight(48)),
        ),
        inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
        useMaterial3: true,
      ),
      home: ValueListenableBuilder<bool>(
        valueListenable: api.signedIn,
        builder: (_, signedIn, __) => signedIn ? const HomeScreen() : const ActivateScreen(),
      ),
    );
  }
}

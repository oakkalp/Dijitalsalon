import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:digimobil_new/providers/event_provider.dart';
import 'package:digimobil_new/screens/login_screen.dart';
import 'package:digimobil_new/screens/instagram_home_screen.dart';
import 'package:digimobil_new/screens/instagram_profile_screen.dart';
import 'package:digimobil_new/screens/join_event_screen.dart';
import 'package:digimobil_new/screens/event_detail_screen.dart';
import 'package:digimobil_new/utils/colors.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => EventProvider()),
      ],
      child: MaterialApp(
        title: 'Digital Salon',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          primarySwatch: Colors.blue,
          scaffoldBackgroundColor: Colors.white,
          appBarTheme: const AppBarTheme(
            backgroundColor: Colors.white,
            foregroundColor: Colors.black,
            elevation: 0,
          ),
          elevatedButtonTheme: ElevatedButtonThemeData(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
          ),
          inputDecorationTheme: InputDecorationTheme(
            filled: true,
            fillColor: Colors.grey.shade100,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: BorderSide.none,
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(8),
              borderSide: const BorderSide(color: AppColors.primary),
            ),
            labelStyle: const TextStyle(color: Colors.grey),
            hintStyle: const TextStyle(color: Colors.grey),
          ),
        ),
        home: const AuthWrapper(),
        routes: {
          '/login': (context) => const LoginScreen(),
          '/home': (context) => const InstagramMainScreen(),
        },
      ),
    );
  }
}

class InstagramMainScreen extends StatefulWidget {
  const InstagramMainScreen({super.key});

  @override
  State<InstagramMainScreen> createState() => _InstagramMainScreenState();
}

class _InstagramMainScreenState extends State<InstagramMainScreen> {
  int _selectedIndex = 0;

  List<Widget> get _pages => [
    const InstagramHomeScreen(),
    JoinEventScreen(onEventJoined: () {
      // Navigate to the joined event's detail screen
      final eventProvider = Provider.of<EventProvider>(context, listen: false);
      final joinedEvent = eventProvider.lastJoinedEvent;
      
      if (joinedEvent != null) {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => EventDetailScreen(event: joinedEvent),
          ),
        );
      } else {
        // Fallback: go to home screen
        setState(() {
          _selectedIndex = 0;
        });
      }
    }),
    const EventDetailScreen(event: null), // Placeholder
    const InstagramProfileScreen(),
  ];

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _pages.elementAt(_selectedIndex),
      bottomNavigationBar: BottomNavigationBar(
        iconSize: 30,
        elevation: 0,
        backgroundColor: Colors.white,
        type: BottomNavigationBarType.fixed,
        items: <BottomNavigationBarItem>[
          BottomNavigationBarItem(
            icon: Icon(
              Icons.home,
              color: (_selectedIndex == 0) ? Colors.black : Colors.black54,
            ),
            label: '',
          ),
          BottomNavigationBarItem(
            icon: Icon(
              Icons.add_box_outlined,
              color: (_selectedIndex == 1) ? Colors.black : Colors.black54,
            ),
            label: '',
          ),
          BottomNavigationBarItem(
            icon: Icon(
              Icons.search,
              color: (_selectedIndex == 2) ? Colors.black : Colors.black54,
            ),
            label: '',
          ),
          BottomNavigationBarItem(
            icon: CircleAvatar(
              backgroundImage: Provider.of<AuthProvider>(context).user?.profileImage != null
                  ? NetworkImage(Provider.of<AuthProvider>(context).user!.profileImage!)
                  : null,
              radius: 15,
              backgroundColor: Colors.grey.shade300,
              child: Provider.of<AuthProvider>(context).user?.profileImage == null
                  ? const Icon(Icons.person, size: 20, color: Colors.grey)
                  : null,
            ),
            label: '',
          ),
        ],
        onTap: _onItemTapped,
        currentIndex: _selectedIndex,
      ),
    );
  }
}

class AuthWrapper extends StatelessWidget {
  const AuthWrapper({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, authProvider, child) {
        if (authProvider.isLoading) {
          return Scaffold(
            backgroundColor: Colors.white,
            body: Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    width: 100,
                    height: 100,
                    decoration: BoxDecoration(
                      gradient: AppColors.primaryGradient,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Icon(
                      Icons.event,
                      color: Colors.white,
                      size: 50,
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'Digital Salon',
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                      color: AppColors.primary,
                    ),
                  ),
                  const SizedBox(height: 20),
                  const CircularProgressIndicator(
                    color: AppColors.primary,
                  ),
                ],
              ),
            ),
          );
        }

        if (authProvider.isLoggedIn) {
          return const InstagramMainScreen();
        } else {
          return const LoginScreen();
        }
      },
    );
  }
}
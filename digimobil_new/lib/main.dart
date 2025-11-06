import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:digimobil_new/providers/event_provider.dart';
import 'package:digimobil_new/providers/theme_provider.dart';
import 'package:digimobil_new/screens/login_screen.dart';
import 'package:digimobil_new/screens/instagram_home_screen.dart';
import 'package:digimobil_new/screens/instagram_profile_screen.dart';
import 'package:digimobil_new/screens/profile_screen.dart';
import 'package:digimobil_new/screens/user_profile_screen.dart';
import 'package:digimobil_new/screens/join_event_screen.dart';
import 'package:digimobil_new/screens/event_detail_screen.dart';
import 'package:digimobil_new/screens/user_search_screen.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/theme_colors.dart';
import 'package:digimobil_new/services/firebase_service.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/widgets/media_viewer_modal.dart';
import 'package:digimobil_new/screens/event_detail_screen.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/screens/notifications_screen.dart';
import 'package:digimobil_new/screens/forgot_password_screen.dart';
import 'package:digimobil_new/screens/reset_password_screen.dart';
import 'package:digimobil_new/screens/verify_code_screen.dart';
import 'package:app_links/app_links.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // ✅ Firebase başlat
  await FirebaseService.initialize();
  
  runApp(const MyApp());
}

// ✅ Global navigator key (bildirime tıklayınca yönlendirme için)
final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

// ✅ Bildirime tıklayınca yönlendirme (global function)
void handleNotificationTap(Map<String, dynamic> data) {
  if (kDebugMode) {
    debugPrint('📱 Handling notification tap: $data');
  }
  
  final navigator = navigatorKey.currentState;
  if (navigator == null) {
    if (kDebugMode) {
      debugPrint('❌ Navigator is null, cannot navigate');
    }
    return;
  }
  
  final type = data['type'];
  final mediaId = data['media_id'];
  final eventId = data['event_id'];
  
  if (kDebugMode) {
    debugPrint('📱 Notification type: $type, media_id: $mediaId, event_id: $eventId');
  }
  
  // ✅ Önce bildirimler sayfasına git, sonra medyayı aç
  // Bildirimler sayfasına yönlendir
  navigator.pushNamed('/notifications').then((_) {
    // Bildirimler sayfası açıldıktan sonra medyayı aç
    if ((type == 'like' || type == 'comment') && mediaId != null && eventId != null) {
      // Kısa bir gecikme ile medyayı aç (sayfa açılsın diye)
      Future.delayed(const Duration(milliseconds: 300), () {
        openMediaFromNotification(mediaId, eventId, navigator);
      });
    }
  });
}

// ✅ Bildirimden medyayı aç (global function)
Future<void> openMediaFromNotification(
  String mediaIdStr,
  String eventIdStr,
  NavigatorState navigator,
) async {
  try {
    final mediaId = int.tryParse(mediaIdStr);
    final eventId = int.tryParse(eventIdStr);
    
    if (mediaId == null || eventId == null) {
      if (kDebugMode) {
        debugPrint('❌ Invalid media_id or event_id: $mediaIdStr, $eventIdStr');
      }
      return;
    }
    
    if (kDebugMode) {
      debugPrint('📱 Opening media: media_id=$mediaId, event_id=$eventId');
    }
    
    final apiService = ApiService();
    
    // ✅ Etkinlik bilgilerini al
    final events = await apiService.getEvents();
    final event = events.firstWhere(
      (e) => e.id == eventId,
      orElse: () => throw Exception('Event not found'),
    );
    
    // ✅ Etkinlik medyalarını al
    final mediaData = await apiService.getMedia(eventId, page: 1, limit: 100);
    final mediaList = (mediaData['media'] as List<dynamic>?)
        ?.map((e) => e as Map<String, dynamic>)
        .toList() ?? [];
    
    // ✅ Açılacak medyayı bul
    final mediaIndex = mediaList.indexWhere((m) => m['id'] == mediaId);
    if (mediaIndex == -1) {
      if (kDebugMode) {
        debugPrint('❌ Media not found in event media list');
      }
      return;
    }
    
    // ✅ MediaViewerModal'ı aç
    navigator.push(
      MaterialPageRoute(
        builder: (context) => MediaViewerModal(
          mediaList: mediaList,
          initialIndex: mediaIndex,
        ),
      ),
    );
  } catch (e) {
    if (kDebugMode) {
      debugPrint('❌ Error opening media from notification: $e');
    }
  }
}

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  final _appLinks = AppLinks();
  
  @override
  void initState() {
    super.initState();
    _initDeepLinks();
  }
  
  void _initDeepLinks() {
    // ✅ App açıkken deep link'i dinle
    _appLinks.uriLinkStream.listen((uri) {
      _handleDeepLink(uri);
    }, onError: (err) {
      if (kDebugMode) {
        debugPrint('❌ Deep link error: $err');
      }
    });
    
    // ✅ App kapalıyken açıldığında deep link'i kontrol et
    _appLinks.getInitialLink().then((uri) {
      if (uri != null) {
        _handleDeepLink(uri);
      }
    }).catchError((err) {
      if (kDebugMode) {
        debugPrint('❌ Initial deep link error: $err');
      }
    });
  }
  
  void _handleDeepLink(Uri uri) {
    if (kDebugMode) {
      debugPrint('🔗 Deep link received: $uri');
    }
    
    final navigator = navigatorKey.currentState;
    if (navigator == null) {
      if (kDebugMode) {
        debugPrint('❌ Navigator is null, cannot handle deep link');
      }
      return;
    }
    
    // ✅ Reset password deep link: digimobil://reset-password?token=xxx
    if (uri.scheme == 'digimobil' && uri.host == 'reset-password') {
      final token = uri.queryParameters['token'];
      if (token != null && token.isNotEmpty) {
        if (kDebugMode) {
          debugPrint('🔗 Navigating to reset password with token: $token');
        }
        
        // ✅ Reset password ekranına git
        navigator.pushNamed(
          '/reset-password',
          arguments: {'token': token},
        );
      } else {
        if (kDebugMode) {
          debugPrint('❌ Reset password token is missing');
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    // ✅ Notification tap handler'ı kur
    final firebaseService = FirebaseService();
    firebaseService.onNotificationTap = handleNotificationTap;
    
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => EventProvider()),
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
      ],
      child: Consumer<ThemeProvider>(
        builder: (context, themeProvider, _) {
          return MaterialApp(
            navigatorKey: navigatorKey, // ✅ Global navigator key
            title: 'Digital Salon',
            debugShowCheckedModeBanner: false,
            theme: AppTheme.lightTheme(),
            darkTheme: AppTheme.darkTheme(),
            themeMode: themeProvider.themeMode,
            home: const AuthWrapper(),
            routes: {
              '/login': (context) => const LoginScreen(),
              '/home': (context) => const InstagramMainScreen(),
              '/profile': (context) => const ProfileScreen(),
              '/notifications': (context) => const NotificationsScreen(),
              '/forgot-password': (context) => const ForgotPasswordScreen(),
              '/reset-password': (context) {
                final args = ModalRoute.of(context)!.settings.arguments as Map<String, dynamic>;
                return ResetPasswordScreen(token: args['token'] as String);
              },
            },
          );
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
      // ✅ Event detay sayfası açılmayacak, sadece ana sayfaya dönülecek
      // ✅ Event kartı real-time görünecek
      setState(() {
        _selectedIndex = 0; // Ana sayfaya dön
      });
    }),
    const UserSearchScreen(), // Changed from EventDetailScreen to UserSearchScreen
    const UserProfileScreen(),
  ];

  void _onItemTapped(int index) {
    print('🔍 NAVIGATION DEBUG - Bottom nav tapped: index $index');
    if (index == 3) {
      // Navigate to profile screen
      print('🔍 NAVIGATION DEBUG - Navigating to /profile route (ProfileScreen)');
      Navigator.pushNamed(context, '/profile');
    } else {
      print('🔍 NAVIGATION DEBUG - Switching to page index $index');
      setState(() {
        _selectedIndex = index;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _pages.elementAt(_selectedIndex),
      bottomNavigationBar: BottomNavigationBar(
        iconSize: 30,
        elevation: 0,
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        type: BottomNavigationBarType.fixed,
        selectedItemColor: ThemeColors.primary(context),
        unselectedItemColor: Theme.of(context).brightness == Brightness.dark 
            ? Colors.grey[400] 
            : Colors.grey[600],
        items: <BottomNavigationBarItem>[
          BottomNavigationBarItem(
            icon: Icon(
              Icons.home,
              color: _selectedIndex == 0 
                  ? ThemeColors.primary(context)
                  : (Theme.of(context).brightness == Brightness.dark ? Colors.grey[400] : Colors.grey[600]),
            ),
            label: '',
          ),
          BottomNavigationBarItem(
            icon: Icon(
              Icons.add_box_outlined,
              color: _selectedIndex == 1 
                  ? ThemeColors.primary(context)
                  : (Theme.of(context).brightness == Brightness.dark ? Colors.grey[400] : Colors.grey[600]),
            ),
            label: '',
          ),
          BottomNavigationBarItem(
            icon: Icon(
              Icons.search,
              color: _selectedIndex == 2 
                  ? ThemeColors.primary(context)
                  : (Theme.of(context).brightness == Brightness.dark ? Colors.grey[400] : Colors.grey[600]),
            ),
            label: '',
          ),
          BottomNavigationBarItem(
            icon: CircleAvatar(
              backgroundImage: Provider.of<AuthProvider>(context).user?.profileImage != null
                  ? NetworkImage(Provider.of<AuthProvider>(context).user!.profileImage!)
                  : null,
              radius: 15,
              backgroundColor: Theme.of(context).brightness == Brightness.dark 
                  ? Colors.grey[700] 
                  : Colors.grey.shade300,
              child: Provider.of<AuthProvider>(context).user?.profileImage == null
                  ? Icon(
                      Icons.person,
                      size: 20,
                      color: Theme.of(context).brightness == Brightness.dark ? Colors.grey[400] : Colors.grey,
                    )
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
        if (kDebugMode) {
          debugPrint('🔍 AuthWrapper - isLoading: ${authProvider.isLoading}');
          debugPrint('🔍 AuthWrapper - isLoggedIn: ${authProvider.isLoggedIn}');
          debugPrint('🔍 AuthWrapper - user: ${authProvider.user?.name}');
        }
        
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
          if (kDebugMode) {
            debugPrint('🔍 AuthWrapper - User is logged in, showing main screen');
          }
          return const InstagramMainScreen();
        } else {
          if (kDebugMode) {
            debugPrint('🔍 AuthWrapper - User is not logged in, showing login screen');
          }
          return const LoginScreen();
        }
      },
    );
  }
}
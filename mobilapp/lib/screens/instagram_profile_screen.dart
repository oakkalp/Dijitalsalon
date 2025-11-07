import 'package:flutter/material.dart';
import 'package:digimobil_new/models/user.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:digimobil_new/screens/event_detail_screen.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:provider/provider.dart';
import 'package:cached_network_image/cached_network_image.dart';

class InstagramProfileScreen extends StatefulWidget {
  const InstagramProfileScreen({super.key});

  @override
  State<InstagramProfileScreen> createState() => _InstagramProfileScreenState();
}

class _InstagramProfileScreenState extends State<InstagramProfileScreen> {
  List<Event> _userEvents = [];
  bool _isLoading = true;
  final ApiService _apiService = ApiService();

  @override
  void initState() {
    super.initState();
    _loadUserEvents();
  }

  Future<void> _loadUserEvents() async {
    try {
      setState(() {
        _isLoading = true;
      });

      final events = await _apiService.getEvents();
      setState(() {
        _userEvents = events;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = Provider.of<AuthProvider>(context).user;
    
    if (user == null) {
      return const Center(
        child: Text('Kullanıcı bilgisi bulunamadı'),
      );
    }

    return DefaultTabController(
      length: 2,
      child: NestedScrollView(
        headerSliverBuilder: (context, index) {
          return [
            _buildAppBar(user),
            _buildProfileInformation(user),
          ];
        },
        body: _buildPublications(),
      ),
    );
  }

  Widget _buildAppBar(User user) {
    return SliverAppBar(
      backgroundColor: Colors.white,
      elevation: 0,
      pinned: true,
      centerTitle: false,
      title: Row(
        mainAxisAlignment: MainAxisAlignment.start,
        children: [
          const Icon(Icons.lock_outline, color: Colors.black),
          const SizedBox(width: 5),
          Text(
            user.name,
            style: const TextStyle(
              color: Colors.black,
              fontSize: 20,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
      actions: [
        IconButton(
          onPressed: () {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Yeni gönderi ekleme özelliği yakında eklenecek'),
                backgroundColor: AppColors.info,
              ),
            );
          },
          icon: const Icon(Icons.add_box_outlined, color: Colors.black),
        ),
        IconButton(
          onPressed: () async {
            await Provider.of<AuthProvider>(context, listen: false).logout();
          },
          icon: const Icon(Icons.logout, color: Colors.black),
        ),
      ],
    );
  }

  Widget _buildProfileInformation(User user) {
    return SliverToBoxAdapter(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildProfileLabelCount(user),
          _buildBio(user),
          const SizedBox(height: 12),
          _buildEditProfile(),
          const SizedBox(height: 15),
          _buildHighlights(),
          _buildTabBar(),
        ],
      ),
    );
  }

  Widget _buildTabBar() {
    return const TabBar(
      indicatorWeight: 1,
      indicatorColor: Colors.black,
      labelColor: Colors.black,
      unselectedLabelColor: Colors.grey,
      tabs: [
        Tab(icon: Icon(Icons.grid_on, color: Colors.black)),
        Tab(icon: Icon(Icons.assignment_ind_outlined, color: Colors.black)),
      ],
    );
  }

  Widget _buildHighlights() {
    return SizedBox(
      height: 80,
      child: ListView.builder(
        itemCount: 6,
        scrollDirection: Axis.horizontal,
        itemBuilder: (_, index) {
          return index != 0
              ? Container(
                  width: 80,
                  alignment: Alignment.topCenter,
                  padding: const EdgeInsets.symmetric(horizontal: 5),
                  child: CircleAvatar(
                    radius: 30,
                    backgroundColor: Colors.grey.shade200,
                    child: const Icon(Icons.event, color: Colors.grey),
                  ),
                )
              : SizedBox(
                  width: 80,
                  child: Column(
                    children: [
                      Container(
                        width: 60,
                        height: 60,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(30),
                          border: Border.all(color: Colors.grey.shade400),
                        ),
                        child: const Icon(Icons.add, size: 25),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'Yeni',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w500,
                          color: Colors.black,
                        ),
                      ),
                    ],
                  ),
                );
        },
      ),
    );
  }

  Widget _buildEditProfile() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Expanded(
            child: Container(
              alignment: Alignment.center,
              height: 35,
              decoration: BoxDecoration(
                color: const Color(0xFFEEEEEE),
                borderRadius: BorderRadius.circular(5),
              ),
              child: const Text(
                'Profili Düzenle',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                  color: Colors.black,
                ),
              ),
            ),
          ),
          const SizedBox(width: 5),
          Container(
            alignment: Alignment.center,
            width: 50,
            height: 35,
            decoration: BoxDecoration(
              color: const Color(0xFFEEEEEE),
              borderRadius: BorderRadius.circular(5),
            ),
            child: const Icon(Icons.add, size: 18),
          ),
        ],
      ),
    );
  }

  Widget _buildBio(User user) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            user.name,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Text(
              'Digital Salon kullanıcısı • ${user.role}',
              style: const TextStyle(color: Colors.black),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildProfileLabelCount(User user) {
    return Padding(
      padding: const EdgeInsets.only(top: 8, right: 10),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: [
          _buildUserStories(user),
          _buildProfileLabelItem('${_userEvents.length}', 'Etkinlikler'),
          _buildProfileLabelItem('${_userEvents.fold(0, (sum, event) => sum + event.participantCount)}', 'Katılımcılar'),
          _buildProfileLabelItem('${_userEvents.fold(0, (sum, event) => sum + event.mediaCount)}', 'Medyalar'),
        ],
      ),
    );
  }

  Widget _buildProfileLabelItem(String count, String label) {
    return Column(
      children: [
        Text(
          count,
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 18,
          ),
        ),
        Text(
          label,
          style: const TextStyle(fontSize: 12),
        ),
      ],
    );
  }

  Widget _buildPublications() {
    var size = MediaQuery.of(context).size;
    return TabBarView(
      children: [
        // Grid view of events
        _isLoading
            ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
            : Wrap(
                spacing: 1,
                runSpacing: 1,
                children: _userEvents.map((event) {
                  return GestureDetector(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => EventDetailScreen(event: event),
                        ),
                      );
                    },
                    child: Container(
                      width: (size.width - 3) / 3,
                      height: (size.width - 3) / 3,
                      decoration: BoxDecoration(
                        color: AppColors.primary.withOpacity(0.1),
                        border: Border.all(
                          color: AppColors.primary.withOpacity(0.3),
                          width: 1,
                        ),
                      ),
                      child: const Center(
                        child: Icon(
                          Icons.event,
                          color: AppColors.primary,
                          size: 30,
                        ),
                      ),
                    ),
                  );
                }).toList(),
              ),
        // Tagged events (placeholder)
        const Center(
          child: Text('Etiketlenmiş etkinlikler yakında eklenecek'),
        ),
      ],
    );
  }

  Widget _buildUserStories(User user) {
    return Column(
      children: [
        SizedBox(
          width: 100,
          height: 100,
          child: Stack(
            children: [
              Container(
                width: 100,
                height: 100,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  image: user.profileImage != null
                      ? DecorationImage(
                          image: NetworkImage(user.profileImage!),
                          fit: BoxFit.cover,
                        )
                      : null,
                  color: user.profileImage == null
                      ? Colors.grey.shade300
                      : null,
                ),
                child: user.profileImage == null
                    ? const Icon(Icons.person, color: Colors.grey, size: 40)
                    : null,
              ),
              Positioned(
                bottom: 0,
                right: 0,
                child: Container(
                  width: 29,
                  height: 29,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white,
                  ),
                  child: const Icon(
                    Icons.add_circle,
                    color: AppColors.primary,
                    size: 29,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

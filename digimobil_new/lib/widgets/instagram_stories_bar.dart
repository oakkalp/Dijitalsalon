import 'package:flutter/material.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/models/user.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/widgets/story_viewer_modal.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/widgets/robust_image_widget.dart';

class InstagramStoriesBar extends StatelessWidget {
  final List<Event> events;
  final List<Map<String, dynamic>> stories;
  final Function(Event)? onEventSelected;
  final VoidCallback? onAddStory;

  const InstagramStoriesBar({
    super.key,
    required this.events,
    required this.stories,
    this.onEventSelected,
    this.onAddStory,
  });

  @override
  Widget build(BuildContext context) {
    final user = Provider.of<AuthProvider>(context).user;
    
    return SizedBox(
      height: 110,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          // Add Story Button (User's own story)
          GestureDetector(
            onTap: onAddStory,
            child: _buildAddStoryButton(user),
          ),
          // Real Stories from API
          ...stories.map((story) => _buildStoryCircle(context, story)).toList(),
        ],
      ),
    );
  }

  Widget _buildAddStoryButton(User? user) {
    return Padding(
      padding: const EdgeInsets.only(right: 20, left: 15, bottom: 10),
      child: Column(
        children: [
          SizedBox(
            width: 65,
            height: 65,
            child: Stack(
              children: [
                Container(
                  width: 65,
                  height: 65,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: user?.profileImage == null
                        ? Colors.grey.shade300
                        : null,
                  ),
                  child: user?.profileImage != null
                      ? ClipOval(
                          child: RobustImageWidget(
                            imageUrl: user!.profileImage!,
                            width: 65,
                            height: 65,
                            fit: BoxFit.cover,
                            placeholder: Container(
                              width: 65,
                              height: 65,
                              color: Colors.grey.shade300,
                              child: const Icon(Icons.person, color: Colors.grey, size: 30),
                            ),
                            errorWidget: Container(
                              width: 65,
                              height: 65,
                              color: Colors.grey.shade300,
                              child: const Icon(Icons.person, color: Colors.grey, size: 30),
                            ),
                          ),
                        )
                      : const Icon(Icons.person, color: Colors.grey, size: 30),
                ),
                Positioned(
                  bottom: 0,
                  right: 0,
                  child: Container(
                    width: 19,
                    height: 19,
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white,
                    ),
                    child: const Icon(
                      Icons.add_circle,
                      color: AppColors.primary,
                      size: 19,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          SizedBox(
            width: 70,
            child: Text(
              'Hikaye Paylaş',
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.black,
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStoryCircle(BuildContext context, Map<String, dynamic> story) {
    return Padding(
      padding: const EdgeInsets.only(right: 20, bottom: 10),
      child: GestureDetector(
        onTap: () => _openStoryViewer(context, story),
        child: Column(
          children: [
            Container(
              width: 68,
              height: 68,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: story['is_viewed'] == false
                    ? const LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Color(0xFFE1306C),
                          Color(0xFFF56040),
                          Color(0xFFF77737),
                          Color(0xFFFCAF45),
                          Color(0xFFFFDC80),
                        ],
                      )
                    : null,
                color: story['is_viewed'] == true ? Colors.grey.shade300 : null,
              ),
              child: Padding(
                padding: const EdgeInsets.all(3.0),
                child: Container(
                  width: 65,
                  height: 65,
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.white, width: 2),
                    shape: BoxShape.circle,
                    color: story['user_avatar'] == null
                        ? Colors.grey.shade300
                        : null,
                  ),
                  child: story['user_avatar'] != null
                      ? ClipOval(
                          child: RobustImageWidget(
                            imageUrl: story['user_avatar'],
                            width: 65,
                            height: 65,
                            fit: BoxFit.cover,
                            placeholder: Container(
                              width: 65,
                              height: 65,
                              color: Colors.grey.shade300,
                              child: const Icon(Icons.person, color: Colors.grey, size: 25),
                            ),
                            errorWidget: Container(
                              width: 65,
                              height: 65,
                              color: Colors.grey.shade300,
                              child: const Icon(Icons.person, color: Colors.grey, size: 25),
                            ),
                          ),
                        )
                      : const Icon(Icons.person, color: Colors.grey, size: 25),
                ),
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: 70,
              child: Text(
                story['user_name'] ?? 'Kullanıcı',
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.black,
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _openStoryViewer(BuildContext context, Map<String, dynamic> story) async {
    try {
      // Get user's stories for this event
      final eventId = events.isNotEmpty ? events.first.id : null;
      if (eventId == null) return;

      final apiService = ApiService();
      final userStories = await apiService.getUserStories(eventId, story['user_id']);
      
      if (userStories.isNotEmpty) {
        // Find the index of the current story
        int initialIndex = 0;
        for (int i = 0; i < userStories.length; i++) {
          if (userStories[i]['id'] == story['id']) {
            initialIndex = i;
            break;
          }
        }

        // Open story viewer
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => StoryViewerModal(
              stories: userStories,
              initialIndex: initialIndex,
              event: events.first, // ✅ Event parametresi eklendi
            ),
            fullscreenDialog: true,
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Hikaye bulunamadı'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Hikaye açılırken hata: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }
}

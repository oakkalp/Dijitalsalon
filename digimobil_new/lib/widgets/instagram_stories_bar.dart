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
import 'package:digimobil_new/screens/camera_screen_modal.dart';
import 'package:image_picker/image_picker.dart';

class InstagramStoriesBar extends StatelessWidget {
  final List<Event> events;
  final List<Map<String, dynamic>> stories;
  final Function(Event)? onEventSelected;
  final VoidCallback? onAddStory;
  final Future<void> Function(XFile file)? onStoryUpload;

  const InstagramStoriesBar({
    super.key,
    required this.events,
    required this.stories,
    this.onEventSelected,
    this.onAddStory,
    this.onStoryUpload,
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
            width: 72,
            height: 72,
            child: Stack(
              children: [
                Container(
                  width: 65,
                  height: 65,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.grey.shade300,
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(3.0),
                    child: Container(
                      width: 66,
                      height: 66,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: AppColors.cardBackground,
                        border: Border.all(color: AppColors.background, width: 3),
                      ),
                      child: ClipOval(
                        child: user?.profileImage != null
                            ? RobustImageWidget(
                                imageUrl: user!.profileImage!,
                                width: 66,
                                height: 66,
                                fit: BoxFit.cover,
                                placeholder: Container(
                                  width: 66,
                                  height: 66,
                                  color: AppColors.surfaceLight,
                                  child: const Icon(Icons.person, color: AppColors.textSecondary, size: 30),
                                ),
                                errorWidget: Container(
                                  width: 66,
                                  height: 66,
                                  color: AppColors.surfaceLight,
                                  child: const Icon(Icons.person, color: AppColors.textSecondary, size: 30),
                                ),
                              )
                            : Container(
                                width: 66,
                                height: 66,
                                color: AppColors.surfaceLight,
                                child: const Icon(Icons.person, color: AppColors.textSecondary, size: 30),
                              ),
                      ),
                    ),
                  ),
                ),
                Positioned(
                  bottom: 0,
                  right: 0,
                  child: Container(
                    width: 22,
                    height: 22,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.primary,
                      border: Border.all(color: Colors.white, width: 2),
                    ),
                    child: const Icon(
                      Icons.add,
                      color: Colors.white,
                      size: 14,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          SizedBox(
            width: 75,
            child: Text(
              'Hikaye Ekle',
              textAlign: TextAlign.center,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.black87,
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
    final bool isViewed = story['is_viewed'] == true;
    
    return Padding(
      padding: const EdgeInsets.only(right: 20, bottom: 10),
      child: GestureDetector(
        onTap: () => _openStoryViewer(context, story),
        child: Column(
          children: [
            Container(
              width: 65,
              height: 65,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: !isViewed
                    ? AppColors.primaryGradient
                    : null,
                color: isViewed ? Colors.grey.shade400 : null,
              ),
              child: Padding(
                padding: const EdgeInsets.all(3.0),
                child: Container(
                  width: 59,
                  height: 59,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white,
                  ),
                  child: ClipOval(
                    child: story['user_avatar'] != null
                        ? RobustImageWidget(
                            imageUrl: story['user_avatar'],
                            width: 66,
                            height: 66,
                            fit: BoxFit.cover,
                            placeholder: Container(
                              width: 66,
                              height: 66,
                              color: AppColors.surfaceLight,
                              child: const Icon(Icons.person, color: AppColors.textSecondary, size: 28),
                            ),
                            errorWidget: Container(
                              width: 66,
                              height: 66,
                              color: AppColors.surfaceLight,
                              child: const Icon(Icons.person, color: AppColors.textSecondary, size: 28),
                            ),
                          )
                        : Container(
                            width: 66,
                            height: 66,
                            color: AppColors.surfaceLight,
                            child: const Icon(Icons.person, color: AppColors.textSecondary, size: 28),
                          ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: 75,
              child: Text(
                story['user_name'] ?? 'Kullanıcı',
                textAlign: TextAlign.center,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: isViewed ? Colors.grey.shade600 : Colors.black87,
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

  void _handleAddStoryTap(BuildContext context) {
    if (onAddStory != null) {
      onAddStory!();
    }
  }
}

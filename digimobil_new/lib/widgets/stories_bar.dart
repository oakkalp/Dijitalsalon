import 'package:flutter/material.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/constants.dart';
import 'package:digimobil_new/widgets/story_viewer_modal.dart';
import 'package:digimobil_new/models/user.dart';

class StoriesBar extends StatelessWidget {
  final List<Map<String, dynamic>> stories;
  final VoidCallback? onAddStory;
  final VoidCallback? onAddStoryFromGallery;
  final VoidCallback? onStoryDeleted;
  final int eventId;
  final User? currentUser;

  const StoriesBar({
    super.key,
    required this.stories,
    required this.eventId,
    this.onAddStory,
    this.onAddStoryFromGallery,
    this.onStoryDeleted,
    this.currentUser,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onPanUpdate: (details) {
        // Swipe up gesture for gallery
        if (details.delta.dy < -10 && onAddStoryFromGallery != null) {
          onAddStoryFromGallery!();
        }
      },
      child: SizedBox(
        height: 110,
        child: ListView(
          scrollDirection: Axis.horizontal,
          children: [
            // Add Story Button
            StoryBackground(
              bottomText: "Hikayen",
              child: GestureDetector(
                onTap: onAddStory,
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(20),
                    gradient: AppColors.primaryGradient,
                  ),
                  child: const Center(
                    child: Icon(
                      Icons.add_box_rounded,
                      color: AppColors.textPrimary,
                      size: 30,
                    ),
                  ),
                ),
              ),
            ),
            // Stories
            ...stories.map((story) => StoryBackground(
              bottomText: story['user_name'] ?? 'Kullanıcı',
              child: GestureDetector(
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => StoryViewerModal(
                        userId: story['user_id'],
                        eventId: eventId,
                        userName: story['user_name'] ?? 'Kullanıcı',
                        userAvatar: story['user_avatar'],
                        currentUser: currentUser,
                        onStoryDeleted: onStoryDeleted,
                      ),
                    ),
                  );
                },
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: story['is_viewed'] == true 
                          ? AppColors.textSecondary.withOpacity(0.3)
                          : AppColors.primary,
                      width: 2,
                    ),
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(18),
                    child: story['user_avatar'] != null
                        ? Image.network(
                            story['user_avatar'],
                            fit: BoxFit.cover,
                            errorBuilder: (context, error, stackTrace) {
                              return Container(
                                color: AppColors.primary.withOpacity(0.1),
                                child: const Center(
                                  child: Icon(
                                    Icons.person,
                                    color: AppColors.primary,
                                    size: 30,
                                  ),
                                ),
                              );
                            },
                          )
                        : Container(
                            color: AppColors.primary.withOpacity(0.1),
                            child: const Center(
                              child: Icon(
                                Icons.person,
                                color: AppColors.primary,
                                size: 30,
                              ),
                            ),
                          ),
                  ),
                ),
              ),
            )),
          ],
        ),
      ),
    );
  }
}

class StoryBackground extends StatelessWidget {
  final Widget child;
  final String bottomText;

  const StoryBackground({
    super.key,
    required this.child,
    required this.bottomText,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 70,
      margin: const EdgeInsets.only(right: 12),
      child: Column(
        children: [
          Expanded(
            child: child,
          ),
          const SizedBox(height: 8),
          Text(
            bottomText,
            style: const TextStyle(
              fontSize: 10,
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w500,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}
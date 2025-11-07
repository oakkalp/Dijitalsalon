import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:digimobil_new/utils/colors.dart';

class SociogramBottomNav extends StatelessWidget {
  final int currentIndex;
  final Function(int) onTap;
  final String? userAvatar;

  const SociogramBottomNav({
    super.key,
    required this.currentIndex,
    required this.onTap,
    this.userAvatar,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 100,
      decoration: BoxDecoration(
        color: AppColors.surface.withOpacity(1),
        borderRadius: const BorderRadius.vertical(
          top: Radius.circular(30),
        ),
        border: Border(
          top: BorderSide(
            color: AppColors.border.withOpacity(0.3),
            width: 1,
          ),
        ),
      ),
      child: Column(
        children: [
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              _buildNavItem(
                icon: Icons.home_filled,
                index: 0,
                isActive: currentIndex == 0,
              ),
              _buildNavItem(
                icon: CupertinoIcons.search,
                index: 1,
                isActive: currentIndex == 1,
              ),
              _buildNavItem(
                icon: Icons.add_box_outlined,
                index: 2,
                isActive: currentIndex == 2,
              ),
              _buildNavItem(
                icon: CupertinoIcons.heart,
                index: 3,
                isActive: currentIndex == 3,
              ),
              _buildNavItem(
                icon: Icons.person,
                index: 4,
                isActive: currentIndex == 4,
                isProfile: true,
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildNavItem({
    required IconData icon,
    required int index,
    required bool isActive,
    bool isProfile = false,
  }) {
    return GestureDetector(
      onTap: () => onTap(index),
      child: Container(
        padding: const EdgeInsets.all(8),
        child: isProfile && userAvatar != null
            ? ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: Image.network(
                  userAvatar!,
                  fit: BoxFit.cover,
                  height: 25,
                  width: 25,
                  errorBuilder: (context, error, stackTrace) {
                    return Container(
                      height: 25,
                      width: 25,
                      decoration: BoxDecoration(
                        color: isActive ? AppColors.primary : AppColors.textTertiary,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(
                        Icons.person,
                        color: AppColors.textPrimary,
                        size: 16,
                      ),
                    );
                  },
                ),
              )
            : Icon(
                icon,
                color: isActive ? AppColors.primary : AppColors.textTertiary,
                size: 28,
              ),
      ),
    );
  }
}


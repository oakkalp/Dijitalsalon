import 'package:flutter/material.dart';
import 'package:digimobil_new/models/user.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:provider/provider.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = Provider.of<AuthProvider>(context).user;
    
    if (user == null) {
      return const Center(
        child: Text('Kullanıcı bilgisi bulunamadı'),
      );
    }

    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          // Profile Header
          Row(
            children: [
              CircleAvatar(
                radius: 40,
                backgroundColor: AppColors.primary.withOpacity(0.1),
                backgroundImage: user.profileImage != null 
                    ? NetworkImage(user.profileImage!) 
                    : null,
                child: user.profileImage == null 
                    ? Icon(
                        Icons.person,
                        size: 40,
                        color: AppColors.primary,
                      )
                    : null,
              ),
              const SizedBox(width: 20),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.name,
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      user.email,
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Rol: ${user.role}',
                      style: const TextStyle(
                        color: AppColors.textTertiary,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 30),
          
          // Action Buttons
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Profil düzenleme özelliği yakında eklenecek'),
                        backgroundColor: AppColors.info,
                      ),
                    );
                  },
                  icon: const Icon(Icons.edit),
                  label: const Text('Profili Düzenle'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: AppColors.textPrimary,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Ayarlar özelliği yakında eklenecek'),
                        backgroundColor: AppColors.info,
                      ),
                    );
                  },
                  icon: const Icon(Icons.settings),
                  label: const Text('Ayarlar'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.textPrimary,
                    side: const BorderSide(color: AppColors.border),
                  ),
                ),
              ),
            ],
          ),
          
          const SizedBox(height: 30),
          
          // Logout Button
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () async {
                await Provider.of<AuthProvider>(context, listen: false).logout();
              },
              icon: const Icon(Icons.logout),
              label: const Text('Çıkış Yap'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.error,
                foregroundColor: AppColors.textPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
class Event {
  final int id;
  final String title;
  final String? description;
  final String? date;
  final String? time; // ✅ Saat alanı eklendi
  final String? location;
  final int creatorId;
  final String? coverPhoto;
  final String? coverPhotoThumbnail;
  final String? coverPhotoPreview;
  final String? qrCode;
  final String? userRole;
  final Map<String, dynamic>? userPermissions; // ✅ Yeni field eklendi
  final String createdAt;
  final int participantCount;
  final int mediaCount;
  final int storyCount; // ✅ Hikaye sayısı eklendi
  final String packageType;
  final int? freeAccessDays; // ✅ Ücretsiz erişim günü

  Event({
    required this.id,
    required this.title,
    this.description,
    this.date,
    this.time, // ✅ Saat alanı eklendi
    this.location,
    required this.creatorId,
    this.coverPhoto,
    this.coverPhotoThumbnail,
    this.coverPhotoPreview,
    this.qrCode,
    this.userRole,
    this.userPermissions, // ✅ Yeni field eklendi
    required this.createdAt,
    required this.participantCount,
    required this.mediaCount,
    required this.storyCount, // ✅ Hikaye sayısı eklendi
    required this.packageType,
    this.freeAccessDays, // ✅ Ücretsiz erişim günü
  });

  factory Event.fromJson(Map<String, dynamic> json) {
    // Debug log for user_permissions
    print('🔍 Event.fromJson - user_permissions: ${json['user_permissions']}');
    print('🔍 Event.fromJson - user_permissions type: ${json['user_permissions'].runtimeType}');
    print('🔍 Event.fromJson - story_count: ${json['story_count']}');
    print('🔍 Event.fromJson - Full JSON: $json');
    
    // ✅ user_permissions List ise Map'e çevir, karışık formatları temizle
    Map<String, dynamic>? userPermissions;
    if (json['user_permissions'] != null) {
      if (json['user_permissions'] is List) {
        // List'i Map'e çevir
        List<dynamic> permissionsList = json['user_permissions'] as List<dynamic>;
        userPermissions = {};
        for (var permission in permissionsList) {
          if (permission is String) {
            userPermissions[permission] = true;
          }
        }
        print('🔍 Event.fromJson - Converted permissions: $userPermissions');
      } else if (json['user_permissions'] is Map) {
        // Map formatında, ama numeric key'leri temizle (array_merge sonucu oluşanlar)
        Map<String, dynamic> rawPermissions = json['user_permissions'] as Map<String, dynamic>;
        userPermissions = {};
        for (var key in rawPermissions.keys) {
          // Sadece string key'leri al (numeric key'leri atla: 0, 1, 2, ...)
          if (key is String && !RegExp(r'^[0-9]+$').hasMatch(key)) {
            userPermissions[key] = rawPermissions[key];
          }
        }
        print('🔍 Event.fromJson - Cleaned permissions: $userPermissions');
      }
    }
    
    try {
      return Event(
        id: json['id'] as int,
        title: json['baslik'] as String,
        description: json['aciklama'] as String?,
        date: json['tarih'] as String?,
        time: json['saat'] as String?, // ✅ Saat alanı eklendi
        location: json['konum'] as String?,
        creatorId: json['olusturan_id'] as int,
               coverPhoto: () {
                 final coverPhoto = json['kapak_fotografi'] as String?;
                 print('🔍 Event.fromJson - CoverPhoto: $coverPhoto');
                 return coverPhoto;
               }(),
               coverPhotoThumbnail: json['kapak_fotografi_thumbnail'] as String?,
               coverPhotoPreview: json['kapak_fotografi_preview'] as String?,
        qrCode: json['qr_kod'] as String?,
        userRole: json['user_role'] as String?,
        userPermissions: userPermissions, // ✅ Düzeltilmiş field
        createdAt: json['created_at'] as String,
        participantCount: json['participant_count'] as int? ?? 0,
        mediaCount: json['media_count'] as int? ?? 0,
        storyCount: json['story_count'] as int? ?? 0, // ✅ Hikaye sayısı eklendi
        packageType: json['package_type'] as String? ?? 'Basic',
        freeAccessDays: json['free_access_days'] as int?,
      );
    } catch (e) {
      print('🔍 Event.fromJson - Error: $e');
      print('🔍 Event.fromJson - JSON keys: ${json.keys.toList()}');
      rethrow;
    }
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'baslik': title,
      'aciklama': description,
      'tarih': date,
      'saat': time, // ✅ Saat alanı eklendi
      'konum': location,
      'olusturan_id': creatorId,
         'kapak_fotografi': coverPhoto,
         'kapak_fotografi_thumbnail': coverPhotoThumbnail,
         'kapak_fotografi_preview': coverPhotoPreview,
         'qr_kod': qrCode,
      'user_role': userRole,
      'created_at': createdAt,
      'participant_count': participantCount,
      'media_count': mediaCount,
      'story_count': storyCount, // ✅ Hikaye sayısı eklendi
      'package_type': packageType,
    };
  }

  @override
  String toString() {
    return 'Event(id: $id, title: $title, date: $date, location: $location)';
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is Event &&
        other.id == id &&
        other.title == title &&
        other.date == date &&
        other.location == location;
  }

  @override
  int get hashCode {
    return id.hashCode ^
        title.hashCode ^
        date.hashCode ^
        location.hashCode;
  }
}


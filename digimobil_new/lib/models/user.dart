class User {
  final int id;
  final String name;
  final String email;
  final String username; // ✅ Kullanıcı adı eklendi
  final String? phone; // ✅ Telefon eklendi
  final String role;
  final String? profileImage;
  final String? sessionKey;

  User({
    required this.id,
    required this.name,
    required this.email,
    required this.username,
    this.phone,
    required this.role,
    this.profileImage,
    this.sessionKey,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] is int ? json['id'] as int : int.parse(json['id'].toString()),
      name: json['name'] as String,
      email: json['email'] as String,
      username: json['username'] as String,
      phone: json['phone'] as String?,
      role: json['role'] as String,
      profileImage: json['profile_image'] as String?,
      sessionKey: json['session_key'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'username': username,
      'phone': phone,
      'role': role,
      'profile_image': profileImage,
      'session_key': sessionKey,
    };
  }

  User copyWith({
    int? id,
    String? name,
    String? email,
    String? username,
    String? phone,
    String? role,
    String? profileImage,
    String? sessionKey,
  }) {
    return User(
      id: id ?? this.id,
      name: name ?? this.name,
      email: email ?? this.email,
      username: username ?? this.username,
      phone: phone ?? this.phone,
      role: role ?? this.role,
      profileImage: profileImage ?? this.profileImage,
      sessionKey: sessionKey ?? this.sessionKey,
    );
  }

  @override
  String toString() {
    return 'User(id: $id, name: $name, email: $email, username: $username, phone: $phone, role: $role)';
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is User &&
        other.id == id &&
        other.name == name &&
        other.email == email &&
        other.username == username &&
        other.phone == phone &&
        other.role == role;
  }

  @override
  int get hashCode {
    return id.hashCode ^
        name.hashCode ^
        email.hashCode ^
        username.hashCode ^
        phone.hashCode ^
        role.hashCode;
  }
}


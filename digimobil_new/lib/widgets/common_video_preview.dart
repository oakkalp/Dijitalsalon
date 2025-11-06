import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

/// ✅ Ortak Video Preview Widget
/// Video önizlemesi için tekrar eden kodları ortaklaştırır
class CommonVideoPreview extends StatefulWidget {
  final VideoPlayerController controller;
  final bool showPlayButton;
  final Color? playButtonColor;
  final double? playButtonSize;
  final BoxFit fit;

  const CommonVideoPreview({
    super.key,
    required this.controller,
    this.showPlayButton = true,
    this.playButtonColor,
    this.playButtonSize,
    this.fit = BoxFit.contain,
  });

  @override
  State<CommonVideoPreview> createState() => _CommonVideoPreviewState();
}

class _CommonVideoPreviewState extends State<CommonVideoPreview> {
  @override
  Widget build(BuildContext context) {
    return AspectRatio(
      aspectRatio: widget.controller.value.aspectRatio,
      child: Stack(
        fit: StackFit.expand,
        children: [
          VideoPlayer(widget.controller),
          if (widget.showPlayButton)
            Center(
              child: IconButton(
                icon: Icon(
                  widget.controller.value.isPlaying
                      ? Icons.pause
                      : Icons.play_arrow,
                  color: widget.playButtonColor ?? Colors.white,
                  size: widget.playButtonSize ?? 48,
                ),
                onPressed: () {
                  setState(() {
                    if (widget.controller.value.isPlaying) {
                      widget.controller.pause();
                    } else {
                      widget.controller.play();
                    }
                  });
                },
              ),
            ),
        ],
      ),
    );
  }
}

/// ✅ Video Loading Widget
/// Video yüklenirken gösterilecek ortak widget
class VideoLoadingWidget extends StatelessWidget {
  final String? message;
  final Color? color;

  const VideoLoadingWidget({
    super.key,
    this.message,
    this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          CircularProgressIndicator(
            color: color ?? Colors.white,
          ),
          if (message != null) ...[
            const SizedBox(height: 16),
            Text(
              message!,
              style: TextStyle(
                color: (color ?? Colors.white).withOpacity(0.7),
                fontSize: 16,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// ✅ Video Error Widget
/// Video yüklenirken hata durumunda gösterilecek ortak widget
class VideoErrorWidget extends StatelessWidget {
  final String? message;
  final Color? color;

  const VideoErrorWidget({
    super.key,
    this.message,
    this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.error_outline,
            color: color ?? Colors.white,
            size: 48,
          ),
          const SizedBox(height: 16),
          Text(
            message ?? 'Video yüklenemedi',
            style: TextStyle(
              color: (color ?? Colors.white).withOpacity(0.7),
              fontSize: 16,
            ),
          ),
        ],
      ),
    );
  }
}


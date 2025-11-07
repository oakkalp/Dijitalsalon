import 'package:flutter/material.dart';
import 'package:digimobil_new/widgets/shimmer_loading.dart';

/// Event detail screen shimmer skeleton
class EventDetailShimmer extends StatelessWidget {
  const EventDetailShimmer({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Stories bar shimmer
          Container(
            height: 100,
            padding: const EdgeInsets.symmetric(vertical: 10),
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 10),
              itemCount: 5,
              itemBuilder: (context, index) {
                return Padding(
                  padding: const EdgeInsets.only(right: 12),
                  child: Column(
                    children: [
                      ShimmerLoading(
                        width: 60,
                        height: 60,
                        borderRadius: BorderRadius.circular(30),
                      ),
                      const SizedBox(height: 4),
                      ShimmerLoading(width: 50, height: 12),
                    ],
                  ),
                );
              },
            ),
          ),
          const Divider(),
          
          // Post cards shimmer
          ...List.generate(3, (index) => _buildPostCardShimmer()),
        ],
      ),
    );
  }

  Widget _buildPostCardShimmer() {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 0, vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header (user info)
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                ShimmerLoading(
                  width: 40,
                  height: 40,
                  borderRadius: BorderRadius.circular(20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ShimmerLoading(width: 120, height: 16),
                      const SizedBox(height: 4),
                      ShimmerLoading(width: 80, height: 12),
                    ],
                  ),
                ),
              ],
            ),
          ),
          
          // Image
          ShimmerLoading(
            width: double.infinity,
            height: 300,
          ),
          
          // Actions
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ShimmerLoading(width: 100, height: 16),
                const SizedBox(height: 8),
                ShimmerLoading(width: 150, height: 14),
                const SizedBox(height: 4),
                ShimmerLoading(width: 200, height: 14),
              ],
            ),
          ),
        ],
      ),
    );
  }
}


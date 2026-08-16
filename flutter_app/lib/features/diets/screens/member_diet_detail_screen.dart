import 'package:flutter/material.dart';
import 'package:gymxbook/core/widgets/ui.dart';

// UX Batch 4: member-specific assigned diet detail view.
class MemberDietDetailScreen extends StatelessWidget {
  final Map<String, dynamic> diet;
  final String memberName;
  const MemberDietDetailScreen({super.key, required this.diet, required this.memberName});
  @override
  Widget build(BuildContext context) {
    final meals = (diet['meals'] as List?) ?? const [];
    return Scaffold(appBar: AppBar(title: Text('$memberName • Diet')), body: ListView(padding: const EdgeInsets.all(16), children: [
      Container(padding: const EdgeInsets.all(20),decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient,borderRadius: BorderRadius.circular(24)),child: Column(crossAxisAlignment: CrossAxisAlignment.start,children:[Text(diet['title']??'Diet Plan',style:context.typo.titleLarge?.copyWith(color:Colors.white)),const SizedBox(height:5),Text(diet['goal']??'Personalized meal plan',style:context.typo.bodyMedium?.copyWith(color:Colors.white70)),const SizedBox(height:12),StatusBadge((diet['status']??'active').toString().toUpperCase(),color:AppTheme.success,soft:false)])),
      const SizedBox(height:18),Text('Meals',style:context.typo.titleLarge),
      ...meals.map((raw){final m=Map<String,dynamic>.from(raw as Map);return Padding(padding:const EdgeInsets.only(top:10),child:SurfaceCard(child:Row(crossAxisAlignment:CrossAxisAlignment.start,children:[IconBadge(Icons.restaurant_rounded,color:AppTheme.success,size:40),const SizedBox(width:10),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(m['meal_name']??'',style:context.typo.titleSmall),if((m['meal_time']??'').toString().isNotEmpty)Text(m['meal_time'],style:context.typo.bodySmall?.copyWith(color:AppTheme.brand)),if((m['food_items']??'').toString().isNotEmpty)Padding(padding:const EdgeInsets.only(top:5),child:Text(m['food_items'],style:context.typo.bodyMedium)),if((m['quantity']??'').toString().isNotEmpty)Text(m['quantity'],style:context.typo.bodySmall?.copyWith(color:context.tokens.textTertiary)),if((m['notes']??'').toString().isNotEmpty)Padding(padding:const EdgeInsets.only(top:4),child:Text(m['notes'],style:context.typo.bodySmall?.copyWith(color:context.tokens.textTertiary)))]))])));}),
      if((diet['general_instructions']??'').toString().isNotEmpty)...[const SizedBox(height:18),Text('Instructions',style:context.typo.titleLarge),const SizedBox(height:8),SurfaceCard(child:Text(diet['general_instructions'],style:context.typo.bodyMedium))]
    ]));
  }
}

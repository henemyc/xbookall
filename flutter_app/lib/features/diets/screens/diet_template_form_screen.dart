import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

// UX Batch 1: full-page Diet Template create/edit form.
class DietTemplateFormScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic>? template;
  const DietTemplateFormScreen({super.key, this.template});
  @override
  ConsumerState<DietTemplateFormScreen> createState() => _DietTemplateFormScreenState();
}

class _DietTemplateFormScreenState extends ConsumerState<DietTemplateFormScreen> {
  late final TextEditingController title, goal, type, calories, protein, water, instructions;
  late List<Map<String, dynamic>> meals;
  bool saving = false;
  bool get editing => widget.template != null;
  @override void initState(){super.initState();final t=widget.template??{};title=TextEditingController(text:t['title']??'');goal=TextEditingController(text:t['goal']??'');type=TextEditingController(text:t['diet_type']??'');calories=TextEditingController(text:t['daily_calories']?.toString()??'');protein=TextEditingController(text:t['protein_target']?.toString()??'');water=TextEditingController(text:t['water_target']?.toString()??'');instructions=TextEditingController(text:t['general_instructions']??'');meals=((t['meals'] as List?)??[]).map((e)=>Map<String,dynamic>.from(e)).toList();if(meals.isEmpty)meals.add({});}
  @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:Text(editing?'Edit Diet Template':'Create Diet Template')),body:SafeArea(child:Column(children:[Expanded(child:ListView(padding:const EdgeInsets.all(16),children:[_hero(),const SizedBox(height:16),TextField(controller:title,decoration:const InputDecoration(labelText:'Template Title*')),const SizedBox(height:10),Row(children:[Expanded(child:TextField(controller:goal,decoration:const InputDecoration(labelText:'Goal'))),const SizedBox(width:10),Expanded(child:TextField(controller:type,decoration:const InputDecoration(labelText:'Diet Type')))]),const SizedBox(height:10),Row(children:[Expanded(child:TextField(controller:calories,keyboardType:TextInputType.number,decoration:const InputDecoration(labelText:'Calories'))),const SizedBox(width:8),Expanded(child:TextField(controller:protein,keyboardType:TextInputType.number,decoration:const InputDecoration(labelText:'Protein g'))),const SizedBox(width:8),Expanded(child:TextField(controller:water,keyboardType:TextInputType.number,decoration:const InputDecoration(labelText:'Water ml')))]),const SizedBox(height:10),TextField(controller:instructions,maxLines:3,decoration:const InputDecoration(labelText:'General Instructions')),const SizedBox(height:22),Row(mainAxisAlignment:MainAxisAlignment.spaceBetween,children:[Text('Meals',style:context.typo.titleLarge),TextButton.icon(onPressed:()=>setState(()=>meals.add({})),icon:const Icon(Icons.add_rounded),label:const Text('Add Meal'))]),...List.generate(meals.length,(i)=>_meal(i))])),Padding(padding:const EdgeInsets.all(16),child:FireButton(label:editing?'Save Changes':'Create Template',onPressed:saving?null:_save))])));
  Widget _hero()=>Container(padding:const EdgeInsets.all(18),decoration:BoxDecoration(gradient:AppTheme.darkHeroGradient,borderRadius:BorderRadius.circular(22)),child:Row(children:[IconBadge(Icons.restaurant_menu_rounded,color:AppTheme.success,size:48),const SizedBox(width:12),Expanded(child:Text('Build a reusable meal plan. Trainers can assign and customize a copy for each member.',style:context.typo.bodyMedium?.copyWith(color:Colors.white,height:1.4)))]));
  Widget _meal(int index) {
    final meal = meals[index];
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: SurfaceCard(
        child: Column(
          children: [
            Row(
              children: [
                Expanded(child: _field(meal, 'meal_time', 'Time (e.g. 01:50 AM)')),
                const SizedBox(width: 8),
                Expanded(child: _field(meal, 'meal_name', 'Meal Name*')),
                IconButton(
                  onPressed: meals.length == 1 ? null : () => setState(() => meals.removeAt(index)),
                  icon: const Icon(Icons.close_rounded, color: AppTheme.danger),
                ),
              ],
            ),
            _field(meal, 'food_items', 'Food Items', lines: 2),
            Row(
              children: [
                Expanded(child: _field(meal, 'quantity', 'Quantity')),
                const SizedBox(width: 8),
                Expanded(child: _field(meal, 'calories', 'Calories', number: true)),
              ],
            ),
            _field(meal, 'notes', 'Meal Notes'),
          ],
        ),
      ),
    );
  }
  Widget _field(Map<String,dynamic> m,String key,String label,{int lines=1,bool number=false}){final c=TextEditingController(text:m[key]?.toString()??'');return Padding(padding:const EdgeInsets.only(top:8),child:TextField(controller:c,maxLines:lines,keyboardType:number?TextInputType.number:null,decoration:InputDecoration(labelText:label),onChanged:(v)=>m[key]=v));}
  Future<void> _save()async{if(title.text.trim().isEmpty||meals.any((m)=>(m['meal_name']??'').toString().trim().isEmpty)){Toast.error(context,'Template title and meal names are required');return;}setState(()=>saving=true);final d={'title':title.text.trim(),'goal':goal.text.trim(),'diet_type':type.text.trim(),'daily_calories':int.tryParse(calories.text),'protein_target':int.tryParse(protein.text),'water_target':int.tryParse(water.text),'general_instructions':instructions.text.trim(),'meals':meals};try{final r=editing?await ref.read(apiClientProvider).updateDietTemplate(widget.template!['id'],d):await ref.read(apiClientProvider).createDietTemplate(d);if(mounted)Navigator.pop(context,r['template']);}catch(_){if(mounted)Toast.error(context,'Could not save diet template');}finally{if(mounted)setState(()=>saving=false);}}
}

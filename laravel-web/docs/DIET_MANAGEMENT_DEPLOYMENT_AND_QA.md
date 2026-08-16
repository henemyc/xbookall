# Diet Management — Deployment and QA

> D9 final release and verification guide.

## phpMyAdmin
1. Take a database backup.
2. Open `sql_create_diet_management_tables.sql`.
3. Run it once in phpMyAdmin.
4. Confirm four tables exist: `diet_templates`, `diet_template_meals`, `member_diets`, `member_diet_meals`.

## Laravel FTP files
Upload to the exact paths:

```text
app/Models/DietTemplate.php
app/Models/DietTemplateMeal.php
app/Models/MemberDiet.php
app/Models/MemberDietMeal.php
app/Http/Controllers/DietController.php
app/Http/Controllers/Panel/PanelDietController.php
app/Services/NotificationDeliveryService.php
routes/api.php
routes/panel.php
resources/views/panel/diets/index.blade.php
```

Then run:

```text
php artisan optimize:clear
```

## Flutter files
Copy into the matching `lib/` path:

```text
core/api/api_client.dart
features/diets/screens/diet_templates_screen.dart
features/diets/screens/assign_member_diet_screen.dart
features/member_diet/screens/member_diet_screen.dart
features/trainer_members/screens/trainer_members_screen.dart
main.dart
```

Then run:

```text
flutter analyze
flutter build appbundle
```

## Mandatory manual QA
- [ ] Owner creates diet template with two meals.
- [ ] Trainer sees own template and shared owner template.
- [ ] Trainer cannot see another trainer private template.
- [ ] Trainer selects template for assigned member and fields auto-fill.
- [ ] Trainer changes one meal and assigns diet.
- [ ] Original template is unchanged.
- [ ] Assigned member sees customized diet in My Diet.
- [ ] Trainer cannot assign diet to another trainer member; API returns 403.
- [ ] Member cannot access another member diet.
- [ ] Diet assignment triggers member notification and opens My Diet.
- [ ] Owner can access `/panel/diets`; staff gets 403.

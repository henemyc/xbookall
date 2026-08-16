# GymXBook Diet Management — Access Policy

> D1: approved permission and access-rule foundation.

## Roles

### Gym Owner / Admin
- Full Diet Template access for the gym.
- Can assign and customize diets for every member in their gym.
- Can view trainer-created templates and member diet history.

### Trainer
- Can create and use diet templates.
- Can assign, view, customize, archive or delete diets only for members where:

```text
trainee_details.trainer_assign = logged-in trainer user ID
```

- Must receive HTTP 403 if a member belongs to another trainer.
- Trainer customization creates/edits a member-specific diet copy; it never changes the master template.

### Staff
- No diet access by default.
- If Gym Owner grants Diet permissions, Laravel requires the exact `diets.*` permission.

### Member / Trainee
- Read-only access to own active assigned diet.
- Cannot access templates, another member diet, create, edit, assign or delete.

## Template ownership

- Gym Owner templates are gym-shared.
- Trainer templates are private to their creator by default.
- A later sharing feature may allow Gym Owner to mark a trainer template as shared.

## Data safety

```text
Diet Template → reusable master
Member Diet → independent copied assignment
```

Changing `Member Diet` meals, targets or notes changes only that member’s diet.

-- D9 production database schema for GymXBook Diet Management
-- GymXBook Diet Management tables
-- Run once in phpMyAdmin on the production GymXBook database.
-- Take a database backup before running.

CREATE TABLE IF NOT EXISTS diet_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NOT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  created_by_type VARCHAR(30) NOT NULL DEFAULT 'admin',
  title VARCHAR(255) NOT NULL,
  goal VARCHAR(120) NULL,
  diet_type VARCHAR(60) NULL,
  daily_calories INT UNSIGNED NULL,
  protein_target INT UNSIGNED NULL,
  water_target INT UNSIGNED NULL,
  general_instructions TEXT NULL,
  is_shared TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX diet_templates_parent_active_index (parent_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS diet_template_meals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  diet_template_id BIGINT UNSIGNED NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  meal_time VARCHAR(30) NULL,
  meal_name VARCHAR(120) NOT NULL,
  food_items TEXT NULL,
  quantity VARCHAR(255) NULL,
  calories INT UNSIGNED NULL,
  protein INT UNSIGNED NULL,
  carbs INT UNSIGNED NULL,
  fats INT UNSIGNED NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX diet_template_meals_template_index (diet_template_id),
  CONSTRAINT diet_template_meals_template_fk FOREIGN KEY (diet_template_id) REFERENCES diet_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS member_diets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NOT NULL,
  member_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NULL,
  assigned_by_user_id BIGINT UNSIGNED NOT NULL,
  assigned_by_type VARCHAR(30) NOT NULL DEFAULT 'admin',
  title VARCHAR(255) NOT NULL,
  goal VARCHAR(120) NULL,
  diet_type VARCHAR(60) NULL,
  daily_calories INT UNSIGNED NULL,
  protein_target INT UNSIGNED NULL,
  water_target INT UNSIGNED NULL,
  general_instructions TEXT NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'active',
  is_customized TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX member_diets_parent_index (parent_id),
  INDEX member_diets_member_status_index (member_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS member_diet_meals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_diet_id BIGINT UNSIGNED NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  meal_time VARCHAR(30) NULL,
  meal_name VARCHAR(120) NOT NULL,
  food_items TEXT NULL,
  quantity VARCHAR(255) NULL,
  calories INT UNSIGNED NULL,
  protein INT UNSIGNED NULL,
  carbs INT UNSIGNED NULL,
  fats INT UNSIGNED NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX member_diet_meals_diet_index (member_diet_id),
  CONSTRAINT member_diet_meals_diet_fk FOREIGN KEY (member_diet_id) REFERENCES member_diets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('student','supervisor','admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_code VARCHAR(30) NOT NULL UNIQUE,
    client_name VARCHAR(150) NOT NULL,
    age TINYINT UNSIGNED NULL,
    sex VARCHAR(30) NULL,
    civil_status VARCHAR(50) NULL,
    religious_affiliation VARCHAR(100) NULL,
    date_of_birth DATE NULL,
    place_of_birth VARCHAR(150) NULL,
    education VARCHAR(150) NULL,
    occupation VARCHAR(150) NULL,
    monthly_income DECIMAL(12,2) NULL,
    present_address VARCHAR(255) NULL,
    home_address VARCHAR(255) NULL,
    status ENUM('Draft','Active','Completed','Archived') NOT NULL DEFAULT 'Draft',
    assigned_student_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(status), INDEX(assigned_student_id),
    FOREIGN KEY (assigned_student_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS case_studies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL UNIQUE,
    presenting_problem TEXT NULL,
    background_client TEXT NULL,
    background_family TEXT NULL,
    background_environment TEXT NULL,
    assessment TEXT NULL,
    goal TEXT NULL,
    evaluation_recommendation TEXT NULL,
    prepared_by VARCHAR(150) NULL,
    prepared_signature VARCHAR(255) NULL,
    noted_by VARCHAR(150) NULL,
    noted_signature VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS family_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    relationship VARCHAR(100) NULL,
    age TINYINT UNSIGNED NULL,
    birthday DATE NULL,
    education VARCHAR(150) NULL,
    occupation VARCHAR(150) NULL,
    civil_status VARCHAR(50) NULL,
    monthly_income DECIMAL(12,2) NULL,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS treatment_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    problems TEXT NOT NULL,
    objectives TEXT NULL,
    activities TEXT NULL,
    responsible_person VARCHAR(150) NULL,
    time_frame VARCHAR(100) NULL,
    expected_output TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    activity_date DATE NOT NULL,
    location VARCHAR(180) NULL,
    responsible_person VARCHAR(150) NULL,
    result_output TEXT NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX(activity_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
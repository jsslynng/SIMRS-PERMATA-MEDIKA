CREATE DATABASE IF NOT EXISTS db_simrs_integrated
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_simrs_integrated;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS units (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_code VARCHAR(30) NOT NULL UNIQUE,
  unit_name VARCHAR(120) NOT NULL,
  unit_type VARCHAR(60) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_code VARCHAR(60) NOT NULL UNIQUE,
  role_name VARCHAR(120) NOT NULL,
  description VARCHAR(255),
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permission_code VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_id BIGINT UNSIGNED NULL,
  full_name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(150) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  module_code VARCHAR(60) NOT NULL,
  action VARCHAR(60) NOT NULL,
  entity_table VARCHAR(80),
  entity_id BIGINT UNSIGNED NULL,
  details_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_module_time(module_code, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS master_config (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  config_key VARCHAR(100) NOT NULL UNIQUE,
  config_value TEXT,
  description VARCHAR(255),
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medical_record_no VARCHAR(30) NOT NULL UNIQUE,
  national_id VARCHAR(30) NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  birth_date DATE NULL,
  sex ENUM('M','F','U') NOT NULL DEFAULT 'U',
  blood_type ENUM('A','B','AB','O','Unknown') DEFAULT 'Unknown',
  phone VARCHAR(30),
  email VARCHAR(150),
  address TEXT,
  emergency_contact_name VARCHAR(150),
  emergency_contact_phone VARCHAR(30),
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_patient_name(full_name),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patient_payers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id BIGINT UNSIGNED NOT NULL,
  payer_type ENUM('self_pay','BPJS','insurance','other') NOT NULL DEFAULT 'self_pay',
  payer_name VARCHAR(150),
  member_no VARCHAR(80),
  valid_from DATE NULL,
  valid_until DATE NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  verification_status ENUM('pending','verified','rejected','expired') NOT NULL DEFAULT 'pending',
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_payer_patient(patient_id, is_primary)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doctors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL UNIQUE,
  unit_id BIGINT UNSIGNED NULL,
  license_no VARCHAR(80),
  specialty VARCHAR(100),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_code VARCHAR(40) NOT NULL UNIQUE,
  service_name VARCHAR(150) NOT NULL,
  service_type ENUM('consultation','procedure','lab','radiology','pharmacy','room','other') NOT NULL DEFAULT 'other',
  unit_id BIGINT UNSIGNED NULL,
  default_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_no VARCHAR(40) NOT NULL UNIQUE,
  patient_id BIGINT UNSIGNED NOT NULL,
  doctor_id BIGINT UNSIGNED NULL,
  unit_id BIGINT UNSIGNED NOT NULL,
  appointment_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NULL,
  complaint TEXT,
  status ENUM('Booked','Confirmed','Checked-in','Completed','Cancelled','Rescheduled','No-show') NOT NULL DEFAULT 'Booked',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
  FOREIGN KEY (unit_id) REFERENCES units(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_appointment_schedule(unit_id, appointment_date, start_time),
  INDEX idx_appointment_patient(patient_id, appointment_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS encounters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_no VARCHAR(40) NOT NULL UNIQUE,
  patient_id BIGINT UNSIGNED NOT NULL,
  appointment_id BIGINT UNSIGNED NULL,
  unit_id BIGINT UNSIGNED NOT NULL,
  encounter_type ENUM('ED','outpatient','inpatient','supporting_service') NOT NULL,
  arrival_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  discharge_at DATETIME NULL,
  priority ENUM('routine','urgent','emergency') NOT NULL DEFAULT 'routine',
  status ENUM('registered','waiting','in_progress','admitted','transferred','discharged','cancelled') NOT NULL DEFAULT 'registered',
  payer_id BIGINT UNSIGNED NULL,
  registered_by BIGINT UNSIGNED NULL,
  chief_complaint TEXT,
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  FOREIGN KEY (unit_id) REFERENCES units(id),
  FOREIGN KEY (payer_id) REFERENCES patient_payers(id) ON DELETE SET NULL,
  FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_encounter_patient(patient_id, arrival_at),
  INDEX idx_encounter_unit_status(unit_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queues (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  queue_no VARCHAR(30) NOT NULL,
  queue_date DATE NOT NULL,
  status ENUM('waiting','called','serving','completed','cancelled') NOT NULL DEFAULT 'waiting',
  called_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_queue(encounter_id),
  UNIQUE KEY uq_queue_number(queue_date, queue_no),
  FOREIGN KEY (encounter_id) REFERENCES encounters(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS triage_assessments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  assessed_by BIGINT UNSIGNED NOT NULL,
  triage_category ENUM('red','yellow','green','black') NOT NULL,
  systolic_bp SMALLINT UNSIGNED NULL,
  diastolic_bp SMALLINT UNSIGNED NULL,
  pulse SMALLINT UNSIGNED NULL,
  respiratory_rate SMALLINT UNSIGNED NULL,
  temperature_c DECIMAL(4,1) NULL,
  spo2 TINYINT UNSIGNED NULL,
  pain_score TINYINT UNSIGNED NULL,
  notes TEXT,
  assessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  FOREIGN KEY (assessed_by) REFERENCES users(id),
  INDEX idx_triage_encounter(encounter_id, assessed_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clinical_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  note_type ENUM('anamnesis','physical_exam','progress','nursing','consultation','discharge_summary','other') NOT NULL,
  subjective TEXT,
  objective TEXT,
  assessment TEXT,
  plan TEXT,
  signed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  FOREIGN KEY (author_id) REFERENCES users(id),
  INDEX idx_notes_encounter(encounter_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS diagnoses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  code_system VARCHAR(30) DEFAULT 'ICD-10',
  diagnosis_code VARCHAR(30),
  diagnosis_name VARCHAR(255) NOT NULL,
  diagnosis_type ENUM('primary','secondary','working','differential') NOT NULL DEFAULT 'primary',
  recorded_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS procedures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NULL,
  procedure_name VARCHAR(180) NOT NULL,
  notes TEXT,
  performed_by BIGINT UNSIGNED NULL,
  performed_at DATETIME NULL,
  status ENUM('ordered','in_progress','completed','cancelled') NOT NULL DEFAULT 'ordered',
  FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
  FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS nursing_assessments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  nurse_id BIGINT UNSIGNED NOT NULL,
  assessment TEXT,
  fall_risk VARCHAR(50),
  pain_score TINYINT UNSIGNED NULL,
  interventions TEXT,
  patient_response TEXT,
  education TEXT,
  handover_notes TEXT,
  assessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  FOREIGN KEY (nurse_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  admission_no VARCHAR(40) NOT NULL UNIQUE,
  admitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  attending_doctor_id BIGINT UNSIGNED NULL,
  status ENUM('waiting_bed','admitted','transferred','discharged','cancelled') NOT NULL DEFAULT 'waiting_bed',
  discharged_at DATETIME NULL,
  discharge_summary TEXT,
  FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  FOREIGN KEY (attending_doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rooms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_id BIGINT UNSIGNED NOT NULL,
  room_code VARCHAR(40) NOT NULL UNIQUE,
  room_name VARCHAR(100) NOT NULL,
  room_class VARCHAR(60),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS beds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  bed_code VARCHAR(40) NOT NULL UNIQUE,
  status ENUM('available','occupied','maintenance','reserved') NOT NULL DEFAULT 'available',
  FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bed_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_id BIGINT UNSIGNED NOT NULL,
  bed_id BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  released_at DATETIME NULL,
  assigned_by BIGINT UNSIGNED NULL,
  FOREIGN KEY (admission_id) REFERENCES admissions(id),
  FOREIGN KEY (bed_id) REFERENCES beds(id),
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_bed_assignment_active(bed_id, released_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clinical_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(40) NOT NULL UNIQUE,
  encounter_id BIGINT UNSIGNED NOT NULL,
  ordered_by BIGINT UNSIGNED NOT NULL,
  order_type ENUM('laboratory','radiology','medication','procedure','consultation','diet','other') NOT NULL,
  priority ENUM('routine','urgent','stat') NOT NULL DEFAULT 'routine',
  clinical_indication TEXT,
  instructions TEXT,
  status ENUM('draft','submitted','accepted','in_progress','completed','cancelled','rejected') NOT NULL DEFAULT 'submitted',
  ordered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  FOREIGN KEY (ordered_by) REFERENCES users(id),
  INDEX idx_orders_encounter(encounter_id, ordered_at),
  INDEX idx_orders_type_status(order_type, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lab_catalog (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_code VARCHAR(40) NOT NULL UNIQUE,
  test_name VARCHAR(150) NOT NULL,
  specimen_type VARCHAR(100),
  unit VARCHAR(40),
  reference_range VARCHAR(120),
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lab_order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clinical_order_id BIGINT UNSIGNED NOT NULL,
  lab_test_id BIGINT UNSIGNED NOT NULL,
  specimen_no VARCHAR(50) NULL UNIQUE,
  specimen_status ENUM('not_collected','collected','received','rejected') NOT NULL DEFAULT 'not_collected',
  result_value TEXT,
  result_unit VARCHAR(40),
  reference_range VARCHAR(120),
  abnormal_flag ENUM('normal','low','high','critical','unknown') NOT NULL DEFAULT 'unknown',
  result_status ENUM('pending','in_progress','validated','released') NOT NULL DEFAULT 'pending',
  collected_at DATETIME NULL,
  validated_by BIGINT UNSIGNED NULL,
  validated_at DATETIME NULL,
  FOREIGN KEY (clinical_order_id) REFERENCES clinical_orders(id),
  FOREIGN KEY (lab_test_id) REFERENCES lab_catalog(id),
  FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS radiology_catalog (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_code VARCHAR(40) NOT NULL UNIQUE,
  exam_name VARCHAR(150) NOT NULL,
  modality VARCHAR(50),
  body_part VARCHAR(100),
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS radiology_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clinical_order_id BIGINT UNSIGNED NOT NULL UNIQUE,
  radiology_exam_id BIGINT UNSIGNED NOT NULL,
  scheduled_at DATETIME NULL,
  performed_at DATETIME NULL,
  performed_by BIGINT UNSIGNED NULL,
  report_text MEDIUMTEXT,
  impression TEXT,
  reported_by BIGINT UNSIGNED NULL,
  finalized_at DATETIME NULL,
  status ENUM('received','verified','scheduled','in_progress','performed','draft_report','final','cancelled') NOT NULL DEFAULT 'received',
  FOREIGN KEY (clinical_order_id) REFERENCES clinical_orders(id),
  FOREIGN KEY (radiology_exam_id) REFERENCES radiology_catalog(id),
  FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS medication_catalog (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medication_code VARCHAR(40) NOT NULL UNIQUE,
  medication_name VARCHAR(180) NOT NULL,
  strength VARCHAR(80),
  dosage_form VARCHAR(80),
  unit VARCHAR(40),
  minimum_stock INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prescriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clinical_order_id BIGINT UNSIGNED NOT NULL UNIQUE,
  prescribed_by BIGINT UNSIGNED NOT NULL,
  verified_by BIGINT UNSIGNED NULL,
  status ENUM('pending_verification','verified','dispensing','dispensed','cancelled') NOT NULL DEFAULT 'pending_verification',
  prescribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dispensed_at DATETIME NULL,
  FOREIGN KEY (clinical_order_id) REFERENCES clinical_orders(id),
  FOREIGN KEY (prescribed_by) REFERENCES users(id),
  FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prescription_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prescription_id BIGINT UNSIGNED NOT NULL,
  medication_id BIGINT UNSIGNED NOT NULL,
  dose VARCHAR(100),
  route VARCHAR(50),
  frequency VARCHAR(100),
  duration VARCHAR(100),
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
  instructions TEXT,
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
  FOREIGN KEY (medication_id) REFERENCES medication_catalog(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location_code VARCHAR(40) NOT NULL UNIQUE,
  location_name VARCHAR(120) NOT NULL,
  location_type ENUM('warehouse','pharmacy','lab','radiology','ward','other') NOT NULL DEFAULT 'warehouse',
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medication_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  batch_no VARCHAR(80) NOT NULL,
  expiry_date DATE NULL,
  quantity_on_hand DECIMAL(14,2) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_inventory_batch(medication_id, location_id, batch_no),
  FOREIGN KEY (medication_id) REFERENCES medication_catalog(id),
  FOREIGN KEY (location_id) REFERENCES inventory_locations(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_no VARCHAR(50) NOT NULL UNIQUE,
  batch_id BIGINT UNSIGNED NOT NULL,
  transaction_type ENUM('receipt','issue','transfer_in','transfer_out','adjustment','return') NOT NULL,
  quantity DECIMAL(14,2) NOT NULL,
  reference_type VARCHAR(60),
  reference_id BIGINT UNSIGNED NULL,
  notes TEXT,
  performed_by BIGINT UNSIGNED NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (batch_id) REFERENCES inventory_batches(id),
  FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(50) NOT NULL UNIQUE,
  encounter_id BIGINT UNSIGNED NOT NULL,
  patient_payer_id BIGINT UNSIGNED NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  patient_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  payer_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('draft','pending','partially_paid','paid','pending_claim','claimed','cancelled') NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  FOREIGN KEY (patient_payer_id) REFERENCES patient_payers(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invoice_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  service_id BIGINT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  source_module VARCHAR(60),
  source_record_id BIGINT UNSIGNED NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  payment_no VARCHAR(50) NOT NULL UNIQUE,
  method ENUM('cash','debit','credit','transfer','QRIS','other') NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  reference_no VARCHAR(100),
  received_by BIGINT UNSIGNED NULL,
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS insurance_claims (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  claim_no VARCHAR(50) NOT NULL UNIQUE,
  payer_name VARCHAR(150),
  submitted_at DATETIME NULL,
  status ENUM('draft','pending','submitted','verified','rejected','approved','paid') NOT NULL DEFAULT 'draft',
  claim_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT,
  created_by BIGINT UNSIGNED NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS report_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_code VARCHAR(80) NOT NULL,
  scope_unit_id BIGINT UNSIGNED NULL,
  period_start DATE NULL,
  period_end DATE NULL,
  generated_by BIGINT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_json JSON NOT NULL,
  FOREIGN KEY (scope_unit_id) REFERENCES units(id) ON DELETE SET NULL,
  FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_report_period(report_code, period_start, period_end)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patient_transfers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  from_unit_id BIGINT UNSIGNED NULL,
  to_unit_id BIGINT UNSIGNED NOT NULL,
  transfer_reason TEXT,
  status ENUM('requested','accepted','completed','cancelled') NOT NULL DEFAULT 'requested',
  requested_by BIGINT UNSIGNED NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  FOREIGN KEY (encounter_id) REFERENCES encounters(id),
  FOREIGN KEY (from_unit_id) REFERENCES units(id) ON DELETE SET NULL,
  FOREIGN KEY (to_unit_id) REFERENCES units(id),
  FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed only general unit and role reference values. No login users/passwords are created.
INSERT IGNORE INTO units (unit_code, unit_name, unit_type) VALUES
 ('REG','Pendaftaran','administrative'),
 ('ED','Instalasi Gawat Darurat','clinical'),
 ('RAD','Radiologi','diagnostic'),
 ('LAB','Laboratorium','diagnostic'),
 ('PHARM','Farmasi','support'),
 ('OPD','Rawat Jalan','clinical'),
 ('IPD','Rawat Inap','clinical'),
 ('MR','Rekam Medis','administrative'),
 ('BILL','Billing & Insurance','finance'),
 ('WH','Gudang / Inventory','logistics'),
 ('IT','Teknologi Informasi SIMRS','administrative'),
 ('MGT','Manajemen','management');

INSERT IGNORE INTO roles (role_code, role_name, description) VALUES
 ('simrs_admin','Administrator SIMRS','Administrasi teknis dan pengaturan akses'),
 ('it_manager','Manajer TI / Kepala SIMRS','Persetujuan perubahan konfigurasi kritikal'),
 ('registration_staff','Petugas Registrasi','Registrasi dan identifikasi pasien'),
 ('medical_records','Petugas Rekam Medis','Verifikasi dan pengelolaan rekam medis'),
 ('doctor','Dokter','Akses klinis sesuai penugasan'),
 ('ed_staff','Petugas IGD','Administrasi pelayanan IGD'),
 ('triage_nurse','Perawat Triase','Penilaian triase IGD'),
 ('nurse','Perawat','Asuhan keperawatan'),
 ('radiology_ordering_doctor','Dokter Pengorder','Membuat order radiologi'),
 ('radiographer','Radiografer','Pelaksanaan pemeriksaan radiologi'),
 ('radiologist','Dokter Radiologi','Validasi dan finalisasi hasil radiologi'),
 ('lab_staff','Petugas Laboratorium','Pengelolaan pemeriksaan laboratorium'),
 ('pharmacist','Apoteker','Verifikasi dan pelayanan resep'),
 ('pharmacy_technician','Tenaga Teknis Kefarmasian','Penyiapan obat sesuai kewenangan'),
 ('billing_staff','Petugas Billing','Tagihan dan pembayaran'),
 ('insurance_verifier','Verifikator Penjamin','Verifikasi dan klaim penjamin'),
 ('warehouse_staff','Petugas Gudang','Persediaan dan transaksi stok'),
 ('unit_manager','Kepala Unit','Monitoring unit sesuai kewenangan'),
 ('management','Manajemen RS','Dashboard dan laporan sesuai kewenangan'),
 ('auditor','Auditor Internal','Audit dan penelusuran tanpa mengubah transaksi');

INSERT IGNORE INTO permissions (permission_code, description) VALUES
 ('patient.read','Melihat data pasien sesuai kewenangan'),
 ('patient.create','Membuat identitas pasien'),
 ('patient.update','Memperbarui data administratif pasien'),
 ('encounter.create','Membuat kunjungan'),
 ('encounter.read','Melihat kunjungan sesuai penugasan'),
 ('clinical.write','Mencatat data klinis sesuai kewenangan'),
 ('clinical.sign','Menandatangani/finalisasi catatan klinis'),
 ('order.create','Membuat order klinis'),
 ('order.process','Memproses order unit penunjang'),
 ('result.validate','Memvalidasi hasil penunjang'),
 ('billing.manage','Mengelola tagihan dan pembayaran'),
 ('inventory.manage','Mengelola persediaan'),
 ('user.manage','Mengelola akun dan role'),
 ('master.manage','Mengelola master data/configuration'),
 ('report.read','Melihat laporan sesuai cakupan akses'),
 ('audit.read','Melihat audit trail'),
 ('appointment.manage','Mengelola appointment dan jadwal');

-- Map a minimal set of broad permissions; fine-grained authorization remains enforced in PHP.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE
 (r.role_code IN ('simrs_admin','it_manager') AND p.permission_code IN ('user.manage','master.manage','audit.read','report.read'))
 OR (r.role_code IN ('registration_staff') AND p.permission_code IN ('patient.read','patient.create','patient.update','encounter.create','encounter.read','appointment.manage'))
 OR (r.role_code IN ('medical_records') AND p.permission_code IN ('patient.read','patient.update','encounter.read','audit.read'))
 OR (r.role_code IN ('doctor','ed_staff','triage_nurse','nurse','radiology_ordering_doctor','radiographer','radiologist','lab_staff','pharmacist','pharmacy_technician') AND p.permission_code IN ('patient.read','encounter.read'))
 OR (r.role_code IN ('doctor','radiology_ordering_doctor') AND p.permission_code IN ('clinical.write','clinical.sign','order.create'))
 OR (r.role_code IN ('ed_staff','triage_nurse','nurse') AND p.permission_code IN ('clinical.write'))
 OR (r.role_code IN ('radiographer','lab_staff') AND p.permission_code IN ('order.process'))
 OR (r.role_code IN ('radiologist','lab_staff') AND p.permission_code IN ('result.validate'))
 OR (r.role_code IN ('pharmacist','pharmacy_technician') AND p.permission_code IN ('order.process','inventory.manage'))
 OR (r.role_code IN ('billing_staff','insurance_verifier') AND p.permission_code IN ('billing.manage','patient.read','encounter.read'))
 OR (r.role_code IN ('warehouse_staff') AND p.permission_code IN ('inventory.manage'))
 OR (r.role_code IN ('unit_manager','management') AND p.permission_code IN ('report.read'))
 OR (r.role_code IN ('auditor') AND p.permission_code IN ('audit.read','report.read'));

-- Module coverage:
-- Master Data & Configuration: units, services, lab_catalog, radiology_catalog, medication_catalog, master_config
-- Patient Registration: patients, patient_payers, encounters, queues
-- EMR: clinical_notes, diagnoses, procedures
-- CPOE / Order Management: clinical_orders, lab_order_items, radiology_orders, prescriptions
-- Emergency Department: encounters, triage_assessments, clinical_notes, procedures, patient_transfers
-- Radiology: radiology_catalog, radiology_orders
-- Scheduling & Appointment: appointments, doctors, units
-- Nursing: nursing_assessments
-- Outpatient: appointments, encounters, clinical_notes, diagnoses, procedures
-- Reporting & Dashboard: report_snapshots (operational data should be queried read-only from source tables)
-- Inpatient: admissions, rooms, beds, bed_assignments, patient_transfers
-- Laboratory: lab_catalog, lab_order_items
-- Pharmacy: medication_catalog, prescriptions, prescription_items, inventory_batches
-- Authentication & User Management: users, roles, permissions, user_roles, role_permissions, audit_logs
-- Billing & Insurance: patient_payers, invoices, invoice_items, payments, insurance_claims
-- Inventory & Warehouse: inventory_locations, inventory_batches, stock_transactions

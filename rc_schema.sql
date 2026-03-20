-- ============================================================
--  RC (Registration Certificate) Database Schema
--  Fixed: PRIMARY KEY on rc, NOT NULL on all rc_no FKs
-- ============================================================

CREATE DATABASE IF NOT EXISTS RC_DATA
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE RC_DATA;

CREATE TABLE rc (
    rc_no               VARCHAR(50)     NOT NULL PRIMARY KEY
                                        CHECK (rc_no REGEXP '^[A-Z]{2}[0-9]{2}[A-Z]{2}[0-9]{4}$'),
    registration_date   DATE            NOT NULL,
    vehicle_no          VARCHAR(20)     NOT NULL UNIQUE,
    class               VARCHAR(50),
    model               VARCHAR(100),
    manufacturer        VARCHAR(100),
    fuel_type           VARCHAR(20),
    color               VARCHAR(30),
    engine_no           VARCHAR(50)     UNIQUE,
    chassis_no          VARCHAR(50)     UNIQUE,
    rto                 VARCHAR(100),
    state               VARCHAR(50),
    registration_valid_upto DATE,
    rfid_tag            VARCHAR(100)    UNIQUE
);

-- ------------------------------------------------------------

CREATE TABLE owners (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    rc_no       VARCHAR(50)     NOT NULL,
    name        VARCHAR(100)    NOT NULL,
    father_name VARCHAR(100),
    address     TEXT,
    FOREIGN KEY (rc_no) REFERENCES rc(rc_no) ON DELETE CASCADE
);

-- ------------------------------------------------------------

CREATE TABLE insurance (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    rc_no       VARCHAR(50)     NOT NULL,
    policy_no   VARCHAR(50)     NOT NULL UNIQUE,
    provider    VARCHAR(100),
    valid_upto  DATE,
    FOREIGN KEY (rc_no) REFERENCES rc(rc_no) ON DELETE CASCADE
);

-- ------------------------------------------------------------

CREATE TABLE fitness (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    rc_no       VARCHAR(50)     NOT NULL,
    valid_upto  DATE,
    FOREIGN KEY (rc_no) REFERENCES rc(rc_no) ON DELETE CASCADE
);

-- ------------------------------------------------------------

CREATE TABLE tax (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    rc_no       VARCHAR(50)     NOT NULL,
    paid_upto   DATE,
    FOREIGN KEY (rc_no) REFERENCES rc(rc_no) ON DELETE CASCADE
);

-- ------------------------------------------------------------

CREATE TABLE pollution (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    rc_no       VARCHAR(50)     NOT NULL,
    pucc_no     VARCHAR(50)     NOT NULL UNIQUE,
    valid_upto  DATE,
    FOREIGN KEY (rc_no) REFERENCES rc(rc_no) ON DELETE CASCADE
);

-- ============================================================
--  TEST DATA
-- ============================================================

INSERT INTO rc (rc_no, registration_date, vehicle_no, class, model, manufacturer, fuel_type, color, engine_no, chassis_no, rto, state, registration_valid_upto, rfid_tag) VALUES
('MH12AB1234', '2019-03-15', 'MH12AB1234', 'LMV',  'Swift',         'Maruti Suzuki', 'Petrol', 'White',  'ENG100012349', 'CHS100012349', 'Pune RTO',      'Maharashtra', '2034-03-14', 'RFID100001'),
('DL01CD5678', '2020-07-22', 'DL01CD5678', 'LMV',  'Nexon',         'Tata Motors',   'Diesel', 'Blue',   'ENG200056789', 'CHS200056789', 'Delhi RTO',     'Delhi',       '2035-07-21', 'RFID100002'),
('KA03EF9012', '2018-11-05', 'KA03EF9012', 'LMV',  'Creta',         'Hyundai',       'Petrol', 'Red',    'ENG300090123', 'CHS300090123', 'Bengaluru RTO', 'Karnataka',   '2033-11-04', 'RFID100003'),
('TN09GH3456', '2021-01-30', 'TN09GH3456', 'MCWG', 'Splendor Plus', 'Hero MotoCorp', 'Petrol', 'Black',  'ENG400034567', 'CHS400034567', 'Chennai RTO',   'Tamil Nadu',  '2036-01-29', 'RFID100004'),
('GJ05IJ7890', '2022-06-18', 'GJ05IJ7890', 'LMV',  'Baleno',        'Maruti Suzuki', 'CNG',    'Silver', 'ENG500078901', 'CHS500078901', 'Ahmedabad RTO', 'Gujarat',     '2037-06-17', 'RFID100005');

INSERT INTO owners (rc_no, name, father_name, address) VALUES
('MH12AB1234', 'Rahul Sharma',  'Rajesh Sharma', '12, Shivaji Nagar, Pune, Maharashtra - 411005'),
('DL01CD5678', 'Priya Mehta',   'Suresh Mehta',  '45, Lajpat Nagar, New Delhi - 110024'),
('KA03EF9012', 'Arun Kumar',    'Vijay Kumar',   '8, Indiranagar, Bengaluru, Karnataka - 560038'),
('TN09GH3456', 'Deepa Rajan',   'Mohan Rajan',   '22, T. Nagar, Chennai, Tamil Nadu - 600017'),
('GJ05IJ7890', 'Nikhil Patel',  'Haresh Patel',  '3, Satellite Road, Ahmedabad, Gujarat - 380015');

INSERT INTO insurance (rc_no, policy_no, provider, valid_upto) VALUES
('MH12AB1234', 'POL-MH-2023-001', 'New India Assurance',   '2026-03-14'),
('DL01CD5678', 'POL-DL-2023-002', 'ICICI Lombard',         '2025-07-21'),
('KA03EF9012', 'POL-KA-2023-003', 'Bajaj Allianz',         '2025-11-04'),
('TN09GH3456', 'POL-TN-2023-004', 'HDFC ERGO',             '2026-01-29'),
('GJ05IJ7890', 'POL-GJ-2023-005', 'Oriental Insurance',    '2026-06-17');

INSERT INTO fitness (rc_no, valid_upto) VALUES
('MH12AB1234', '2026-03-14'),
('DL01CD5678', '2025-07-21'),
('KA03EF9012', '2025-11-04'),
('TN09GH3456', '2026-01-29'),
('GJ05IJ7890', '2026-06-17');

INSERT INTO tax (rc_no, paid_upto) VALUES
('MH12AB1234', '2025-03-31'),
('DL01CD5678', '2025-03-31'),
('KA03EF9012', '2024-03-31'),
('TN09GH3456', '2025-03-31'),
('GJ05IJ7890', '2025-03-31');

INSERT INTO pollution (rc_no, pucc_no, valid_upto) VALUES
('MH12AB1234', 'PUCC-MH-10001', '2025-09-14'),
('DL01CD5678', 'PUCC-DL-10002', '2026-01-21'),
('KA03EF9012', 'PUCC-KA-10003', '2025-05-04'),
('TN09GH3456', 'PUCC-TN-10004', '2025-07-29'),
('GJ05IJ7890', 'PUCC-GJ-10005', '2025-12-17');

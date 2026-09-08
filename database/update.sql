ALTER TABLE events ADD COLUMN tan_spray_price INT DEFAULT 0 AFTER venue;

ALTER TABLE registrations 
ADD COLUMN instagram_id VARCHAR(255) NULL AFTER phone,
ADD COLUMN age INT NULL AFTER date_of_birth,
ADD COLUMN height DECIMAL(5,2) NULL AFTER gender,
ADD COLUMN weight DECIMAL(5,2) NULL AFTER height,
ADD COLUMN tan_spray_requested TINYINT(1) DEFAULT 0 AFTER emergency_contact_phone,
ADD COLUMN tan_spray_fee INT DEFAULT 0 AFTER tan_spray_requested,
ADD COLUMN base_total INT DEFAULT 0 AFTER tan_spray_fee,
ADD COLUMN additional_category_discount INT DEFAULT 0 AFTER base_total,
ADD COLUMN total_amount INT NULL AFTER additional_category_discount;

CREATE TABLE registration_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    category_id INT NOT NULL,
    base_fee INT NOT NULL,
    discount INT DEFAULT 0,
    payable INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS hallmark_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    firm_id INT NOT NULL,
    bis_registration_number VARCHAR(50) NOT NULL,
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'processing', 'completed', 'rejected') DEFAULT 'pending',
    total_items INT DEFAULT 0,
    total_weight DECIMAL(10,3) DEFAULT 0.000,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    hallmark_center_id INT,
    hallmark_pro_reference VARCHAR(100),
    remarks TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (firm_id) REFERENCES Firm(id),
    FOREIGN KEY (hallmark_center_id) REFERENCES hallmark_centers(id)
);

CREATE TABLE IF NOT EXISTS hallmark_request_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    metal_type ENUM('Gold', 'Silver', 'Platinum') NOT NULL,
    purity DECIMAL(5,2) NOT NULL,
    weight DECIMAL(10,3) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES hallmark_requests(id)
);

CREATE TABLE IF NOT EXISTS hallmark_centers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    uses_hallmark_pro BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
); 
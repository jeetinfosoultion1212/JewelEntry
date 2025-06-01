CREATE TABLE IF NOT EXISTS Suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    firm_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    gst VARCHAR(50),
    payment_terms TEXT,
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (firm_id) REFERENCES Firm(id) ON DELETE CASCADE,
    INDEX idx_firm_id (firm_id),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 
CREATE TABLE IF NOT EXISTS loans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    firm_id INT NOT NULL,
    customer_id INT NOT NULL,
    loan_amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    loan_duration_months INT NOT NULL,
    start_date DATE NOT NULL,
    collateral_value DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'closed', 'defaulted') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (firm_id) REFERENCES Firm(id),
    FOREIGN KEY (customer_id) REFERENCES customer(id),
    FOREIGN KEY (created_by) REFERENCES Firm_Users(id)
);

CREATE TABLE IF NOT EXISTS loan_collateral_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT NOT NULL,
    material_type VARCHAR(50) NOT NULL,
    purity DECIMAL(5,2) NOT NULL,
    weight DECIMAL(10,3) NOT NULL,
    rate_per_gram DECIMAL(10,2) NOT NULL,
    calculated_value DECIMAL(10,2) NOT NULL,
    description TEXT,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
); 
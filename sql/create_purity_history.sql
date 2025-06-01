CREATE TABLE IF NOT EXISTS Purity_History (
    id INT PRIMARY KEY AUTO_INCREMENT,
    firm_id INT NOT NULL,
    purity DECIMAL(5,2) NOT NULL,
    rate DECIMAL(10,2) NOT NULL,
    updated_by INT,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (firm_id) REFERENCES Firm(id),
    FOREIGN KEY (updated_by) REFERENCES Firm_Users(id)
); 
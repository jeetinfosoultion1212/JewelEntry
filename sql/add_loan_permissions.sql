-- Add loan access permission column to firm_users table
ALTER TABLE firm_users 
ADD COLUMN has_loan_access BOOLEAN DEFAULT FALSE;

-- Update existing admin users to have loan access
UPDATE firm_users 
SET has_loan_access = TRUE 
WHERE role = 'admin' OR role = 'owner';

-- Add loan access to specific users if needed
-- UPDATE firm_users SET has_loan_access = TRUE WHERE id IN (1, 2, 3); 
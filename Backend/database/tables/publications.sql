CREATE TABLE publications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(255) NULL,
    publication_year YEAR NULL,
    publisher VARCHAR(255) NULL,
    department VARCHAR(255) NOT NULL,
    type ENUM('book', 'thesis') NOT NULL,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    shelf_location VARCHAR(255) NULL,
    description TEXT NULL,
    cover_url VARCHAR(255) NULL, -- Cloudinary URL
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

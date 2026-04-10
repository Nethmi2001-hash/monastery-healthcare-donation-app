-- AI Chatbot Database Table
-- Add this to your monastery_healthcare database

-- Chat Logs Table (for analytics and improvements)
CREATE TABLE IF NOT EXISTS chat_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_message TEXT NOT NULL,
    bot_response TEXT NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    session_id VARCHAR(100),
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_language (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample queries for analytics

-- Most common questions
SELECT 
    user_message, 
    COUNT(*) as frequency 
FROM chat_logs 
GROUP BY user_message 
ORDER BY frequency DESC 
LIMIT 10;

-- Language distribution
SELECT 
    language, 
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM chat_logs), 2) as percentage
FROM chat_logs 
GROUP BY language;

-- Daily chat volume
SELECT 
    DATE(created_at) as date,
    COUNT(*) as chat_count
FROM chat_logs 
GROUP BY DATE(created_at) 
ORDER BY date DESC 
LIMIT 30;

-- Average response length
SELECT 
    AVG(LENGTH(bot_response)) as avg_response_length,
    MIN(LENGTH(bot_response)) as min_length,
    MAX(LENGTH(bot_response)) as max_length
FROM chat_logs;

-- ============================================
-- NewsHub Vulnerable Lab - Database Init
-- ============================================

CREATE DATABASE IF NOT EXISTS newshub;
USE newshub;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(150),
    role ENUM('admin','editor','user') DEFAULT 'user',
    bio TEXT,
    avatar VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT
);

-- News/Articles table
CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    summary VARCHAR(500),
    author_id INT,
    category_id INT,
    views INT DEFAULT 0,
    status ENUM('published','draft','hidden') DEFAULT 'published',
    tags VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Comments table
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    news_id INT NOT NULL,
    user_id INT,
    author_name VARCHAR(100),
    content TEXT NOT NULL,
    ip_address VARCHAR(50),
    status ENUM('approved','pending','spam') DEFAULT 'approved',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (news_id) REFERENCES news(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Search logs table (for tracking - SQLi time-based endpoint)
CREATE TABLE search_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    keyword VARCHAR(255),
    user_id INT,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Page views tracking table
CREATE TABLE page_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_url VARCHAR(500),
    ref_url VARCHAR(500),
    session_id VARCHAR(100),
    ip_address VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Secret table (target for SQLi exfiltration)
CREATE TABLE secret_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100),
    config_value TEXT,
    description VARCHAR(255)
);

-- ============================================
-- SEED DATA
-- ============================================

-- Users (passwords are MD5 for simplicity - intentionally weak)
-- admin: admin123, editor: editor123, user1: password123
INSERT INTO users (username, password, email, role, bio) VALUES
('admin', '0192023a7bbd73250516f069df18b500', 'admin@newshub.local', 'admin', 'Quản trị viên hệ thống NewsHub'),
('editor1', '3e4b21380a1b5c92e340e5b39f137da1', 'editor1@newshub.local', 'editor', 'Biên tập viên chuyên mảng công nghệ'),
('thinhnv', '482c811da5d5b4bc6d497ffa98491e38', 'thinhnv@newshub.local', 'user', 'Độc giả yêu thích công nghệ và thể thao'),
('minhlt', '5f4dcc3b5aa765d61d8327deb882cf99', 'minhlt@newshub.local', 'user', 'Nhà báo tự do, yêu thích viết lách'),
('huongnt', '25d55ad283aa400af464c76d713c07ad', 'huongnt@newshub.local', 'user', 'Sinh viên CNTT, đam mê an toàn thông tin');

-- Categories
INSERT INTO categories (name, slug, description) VALUES
('Công nghệ', 'cong-nghe', 'Tin tức công nghệ mới nhất'),
('Thể thao', 'the-thao', 'Bóng đá, tennis và các môn thể thao'),
('Kinh tế', 'kinh-te', 'Tài chính, thị trường, kinh doanh'),
('Giải trí', 'giai-tri', 'Phim ảnh, âm nhạc, celebrity'),
('Xã hội', 'xa-hoi', 'Đời sống, cộng đồng');

-- Articles
INSERT INTO news (title, content, summary, author_id, category_id, views, tags) VALUES
('Trí tuệ nhân tạo thay đổi tương lai ngành giáo dục', 
 '<p>Các mô hình ngôn ngữ lớn (LLM) đang được triển khai rộng rãi trong các trường học trên toàn thế giới. Nhiều nghiên cứu cho thấy học sinh sử dụng AI có kết quả học tập tốt hơn 30% so với nhóm không sử dụng.</p><p>Tuy nhiên, không ít lo ngại về vấn đề đạo đức học thuật và khả năng tư duy độc lập của học sinh cũng được đặt ra. Các chuyên gia giáo dục đang tranh luận về cách tích hợp AI một cách có trách nhiệm.</p>',
 'AI đang cách mạng hóa giáo dục với những kết quả đáng kinh ngạc', 1, 1, 1520, 'AI,giáo dục,công nghệ'),
 
('ChatGPT đạt 100 triệu người dùng trong vòng 2 tháng',
 '<p>OpenAI công bố ChatGPT đã cán mốc 100 triệu người dùng hoạt động hàng tháng chỉ sau 2 tháng ra mắt, trở thành ứng dụng tiêu dùng tăng trưởng nhanh nhất trong lịch sử.</p><p>Con số này vượt xa TikTok (9 tháng) và Instagram (2,5 năm) để đạt cùng mốc. CEO Sam Altman xác nhận thông tin này qua Twitter.</p>',
 'Kỷ lục tăng trưởng người dùng chưa từng có trong lịch sử internet', 2, 1, 3201, 'ChatGPT,OpenAI,AI'),

('Đội tuyển Việt Nam vào chung kết AFF Cup 2024',
 '<p>Sau màn trình diễn xuất sắc, đội tuyển Việt Nam đã vượt qua Thái Lan với tỷ số 2-1 trong trận bán kết AFF Cup 2024 tại Hà Nội. Bàn thắng quyết định đến từ Nguyễn Văn Toàn ở phút 88.</p><p>Hàng triệu người hâm mộ đổ ra đường mừng chiến thắng lịch sử này. HLV Philippe Troussier chia sẻ niềm vui cùng toàn đội.</p>',
 'Chiến thắng nghẹt thở trước Thái Lan đưa Việt Nam vào chung kết', 1, 2, 8920, 'bóng đá,AFF Cup,tuyển Việt Nam'),

('Thị trường chứng khoán Việt Nam lập đỉnh mới',
 '<p>VN-Index vượt mốc 1.300 điểm lần đầu tiên trong lịch sử phiên giao dịch hôm nay với khối lượng giao dịch đạt 1,2 tỷ cổ phiếu. Nhóm cổ phiếu ngân hàng và bất động sản dẫn dắt đà tăng.</p>',
 'VN-Index lần đầu vượt 1.300 điểm - kỷ lục lịch sử', 2, 3, 4350, 'chứng khoán,VN-Index,tài chính'),

('Phim Việt "Mắt Biếc" đạt doanh thu 100 tỷ đồng',
 '<p>Bộ phim chuyển thể từ tiểu thuyết của Nguyễn Nhật Ánh đã chính thức cán mốc 100 tỷ đồng doanh thu phòng vé sau 3 tuần công chiếu, trở thành phim Việt có doanh thu cao nhất năm.</p>',
 'Kỷ lục doanh thu phòng vé của điện ảnh Việt Nam', 2, 4, 2100, 'phim Việt,điện ảnh,Mắt Biếc'),

('An ninh mạng: Hacker tấn công hệ thống ngân hàng',
 '<p>Một nhóm hacker quốc tế đã thực hiện cuộc tấn công quy mô lớn vào hệ thống của nhiều ngân hàng Đông Nam Á. Các chuyên gia bảo mật khuyến cáo người dùng thay đổi mật khẩu ngay lập tức.</p><p>Cục An toàn Thông tin đã vào cuộc điều tra và phối hợp với các tổ chức quốc tế để truy tìm nhóm tội phạm này.</p>',
 'Cảnh báo khẩn: Ngân hàng bị tấn công mạng quy mô lớn', 1, 1, 6780, 'hacker,an ninh mạng,ngân hàng'),

('Startup Việt gọi vốn thành công 10 triệu USD',
 '<p>MedTech startup Docosan vừa hoàn tất vòng gọi vốn Series A trị giá 10 triệu USD từ quỹ đầu tư Mỹ và Singapore. Đây là một trong những thương vụ lớn nhất trong lĩnh vực y tế số tại Việt Nam.</p>',
 'Thành công vang dội của startup y tế số tại thị trường Việt Nam', 2, 3, 1890, 'startup,gọi vốn,fintech'),

('Bí quyết học lập trình từ con số 0',
 '<p>Nhiều người nghĩ rằng lập trình là một kỹ năng quá khó để học nếu bạn không có nền tảng. Tuy nhiên, với phương pháp đúng đắn và sự kiên trì, bất kỳ ai cũng có thể trở thành lập trình viên.</p><p>Bước đầu tiên là chọn ngôn ngữ phù hợp với mục tiêu của bạn: Python cho data science/AI, JavaScript cho web, hay Swift/Kotlin cho mobile app.</p>',
 'Hướng dẫn chi tiết cho người mới bắt đầu học lập trình', 2, 1, 5430, 'lập trình,học code,career');

-- Comments
INSERT INTO comments (news_id, user_id, author_name, content, ip_address) VALUES
(1, 3, 'thinhnv', 'Bài viết rất hay và bổ ích! Cảm ơn tác giả.', '192.168.1.10'),
(1, 4, 'minhlt', 'Tôi đồng ý với quan điểm này. AI đang thay đổi mọi thứ.', '192.168.1.11'),
(1, NULL, 'Khách', 'Thông tin rất hữu ích, mình sẽ chia sẻ cho bạn bè.', '192.168.1.50'),
(2, 5, 'huongnt', 'ChatGPT thật sự ấn tượng! Tôi dùng hàng ngày rồi.', '192.168.1.12'),
(3, 3, 'thinhnv', 'Tuyệt vời! Việt Nam vào chung kết rồi, quá xuất sắc!', '192.168.1.10'),
(3, NULL, 'Khách', 'Đội tuyển đá hay lắm, ủng hộ hết mình!', '192.168.1.55'),
(6, 4, 'minhlt', 'Thật đáng lo ngại. Mọi người cần nâng cao ý thức bảo mật.', '192.168.1.11'),
(8, 5, 'huongnt', 'Mình đang học Python và thấy rất thú vị. Bài này hay!', '192.168.1.12');

-- Secret configs (SQLi exfiltration target)
INSERT INTO secret_configs (config_key, config_value, description) VALUES
('api_secret_key', 'sk-newshub-9f8e7d6c5b4a3210', 'API secret key cho third-party services'),
('smtp_password', 'Sup3rS3cr3tMail!', 'Mật khẩu SMTP server'),
('admin_backup_token', 'bkp_token_xK9mN2pL8qR5wT3v', 'Token backup cho admin'),
('db_encryption_key', 'enc_key_zX7yW4uV1sQ6rP9o', 'Khóa mã hóa database');

-- Search logs seed
INSERT INTO search_logs (keyword, ip_address, user_agent) VALUES
('công nghệ AI', '192.168.1.1', 'Mozilla/5.0'),
('bóng đá AFF Cup', '192.168.1.2', 'Chrome/120.0'),
('chứng khoán', '192.168.1.3', 'Firefox/121.0');
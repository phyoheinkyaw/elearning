DROP DATABASE IF EXISTS elearning_db;

-- Create the database
CREATE DATABASE IF NOT EXISTS elearning_db;
USE elearning_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role TINYINT NOT NULL DEFAULT 1 COMMENT '0: admin, 1: student, 2: instructor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- User profiles table
CREATE TABLE IF NOT EXISTS user_profiles (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    full_name VARCHAR(100),
    proficiency_level CHAR(2) COMMENT 'A1, A2, B1, B2, C1, C2',
    profile_picture VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Courses table
CREATE TABLE IF NOT EXISTS courses (
    course_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    instructor_id INT NOT NULL,
    level CHAR(2) NOT NULL COMMENT 'A1, A2, B1, B2, C1, C2',
    duration VARCHAR(50),
    difficulty_level VARCHAR(20),
    thumbnail_url VARCHAR(255),
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES users(user_id)
);

-- Course materials table
CREATE TABLE IF NOT EXISTS course_materials (
    material_id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content TEXT NOT NULL,
    order_number INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
);

-- Course enrollments table
CREATE TABLE IF NOT EXISTS course_enrollments (
    enrollment_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    course_id INT,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (user_id, course_id)
);

-- User progress table
CREATE TABLE IF NOT EXISTS user_progress (
    progress_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    material_id INT,
    progress INT DEFAULT 0,
    last_accessed TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES course_materials(material_id) ON DELETE CASCADE,
    UNIQUE KEY unique_progress (user_id, material_id)
);

-- Level test questions table
CREATE TABLE IF NOT EXISTS level_test_questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_answer CHAR(1) NOT NULL,
    difficulty_level CHAR(2) NOT NULL COMMENT 'A1, A2, B1, B2, C1, C2'
);

-- Level test results table
CREATE TABLE IF NOT EXISTS level_test_results (
    result_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    score INT NOT NULL,
    assigned_level CHAR(2) NOT NULL COMMENT 'A1, A2, B1, B2, C1, C2',
    test_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Quizzes table
CREATE TABLE IF NOT EXISTS quizzes (
    quiz_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    difficulty_level TINYINT NOT NULL COMMENT '0: beginner, 1: intermediate, 2: advanced',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Quiz attempts table
CREATE TABLE IF NOT EXISTS quiz_attempts (
    attempt_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    quiz_id INT,
    score INT NOT NULL,
    status TINYINT NOT NULL DEFAULT 0 COMMENT '0: in_progress, 1: completed',
    completion_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE
);

-- Quiz questions table
CREATE TABLE IF NOT EXISTS quiz_questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT,
    question_text TEXT NOT NULL,
    question_type TINYINT NOT NULL COMMENT '0: multiple_choice, 1: matching, 2: grammar',
    options JSON NOT NULL,
    correct_answer TEXT NOT NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE
);

-- Quiz answers table: stores each user's answer to each question in each attempt
CREATE TABLE IF NOT EXISTS quiz_answers (
    answer_id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    question_id INT NOT NULL,
    answer TEXT NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(question_id) ON DELETE CASCADE,
    INDEX (user_id),
    INDEX (quiz_id),
    INDEX (question_id),
    UNIQUE KEY uq_attempt_question (attempt_id, question_id)
);

-- Quiz results table
CREATE TABLE IF NOT EXISTS quiz_results (
    result_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    quiz_id INT,
    score INT NOT NULL,
    attempt_id INT,
    completion_time INT,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(attempt_id) ON DELETE CASCADE
);

-- Voice recognition attempts table
CREATE TABLE IF NOT EXISTS voice_recognition_attempts (
    attempt_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    phrase_text TEXT NOT NULL,
    accuracy_score DECIMAL(5,2),
    attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Chatbot conversations table
-- CREATE TABLE IF NOT EXISTS chatbot_conversations (
--     conversation_id INT PRIMARY KEY AUTO_INCREMENT,
--     user_id INT,
--     user_query TEXT NOT NULL,
--     bot_response TEXT NOT NULL,
--     conversation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
-- );

-- Chatbot messages table
CREATE TABLE IF NOT EXISTS chat_messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    sender TINYINT NOT NULL COMMENT '0: user, 1: chatbot',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Resource library table
CREATE TABLE IF NOT EXISTS resource_library (
    resource_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_type TINYINT NOT NULL COMMENT '0: pdf, 1: ebook, 2: worksheet',
    proficiency_level ENUM('A1', 'A2', 'B1', 'B2', 'C1', 'C2') NOT NULL,
    course_id INT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE SET NULL
);

-- Wordscapes game tables

-- Game levels table
CREATE TABLE IF NOT EXISTS wordscapes_levels (
    level_id INT PRIMARY KEY AUTO_INCREMENT,
    difficulty TINYINT NOT NULL,
    given_letters VARCHAR(20) NOT NULL,
    level_number INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User game preferences table
CREATE TABLE IF NOT EXISTS user_game_preferences (
    preference_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    wordscapes_current_level INT DEFAULT 1,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    UNIQUE KEY unique_user_preferences (user_id)
);

-- User progress for Wordscapes
CREATE TABLE IF NOT EXISTS wordscapes_user_progress (
    progress_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    level_id INT NOT NULL,
    found_words JSON,
    score INT NOT NULL DEFAULT 0,
    hints_used INT NOT NULL DEFAULT 0,
    last_played TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (level_id) REFERENCES wordscapes_levels(level_id),
    UNIQUE KEY unique_progress (user_id, level_id)
);

-- Create indexes for better performance
CREATE INDEX idx_wordscapes_progress_user ON wordscapes_user_progress (user_id);
CREATE INDEX idx_wordscapes_progress_level ON wordscapes_user_progress (level_id);

-- Valid words for each level
CREATE TABLE IF NOT EXISTS wordscapes_words (
    word_id INT PRIMARY KEY AUTO_INCREMENT,
    level_id INT NOT NULL,
    word VARCHAR(50) NOT NULL,
    FOREIGN KEY (level_id) REFERENCES wordscapes_levels(level_id),
    UNIQUE KEY unique_word (level_id, word)
);

-- Insert sample data

-- Insert users (password123)
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@example.com', '$2y$10$JfD1NK3z8uGHpd5mXVmBeOjNsvoo/BUc1iQi6ytE7to91XIMw/Pn2', 0),
('john_doe', 'john@example.com', '$2y$10$JfD1NK3z8uGHpd5mXVmBeOjNsvoo/BUc1iQi6ytE7to91XIMw/Pn2', 1),
('instructor1', 'instructor1@example.com', '$2y$10$JfD1NK3z8uGHpd5mXVmBeOjNsvoo/BUc1iQi6ytE7to91XIMw/Pn2', 2),
('instructor2', 'instructor2@example.com', '$2y$10$JfD1NK3z8uGHpd5mXVmBeOjNsvoo/BUc1iQi6ytE7to91XIMw/Pn2', 2);

-- Insert user profiles
INSERT INTO user_profiles (user_id, full_name, proficiency_level) VALUES
(1, 'Admin User', 'C2'),
(2, 'John Doe', 'A2'),
(3, 'Sarah Smith', 'C1'),
(4, 'Michael Johnson', 'C1');

-- Insert courses
INSERT INTO courses (title, description, instructor_id, level, duration, difficulty_level, is_featured) VALUES
('English for Beginners', 'Start your English learning journey with our comprehensive beginner course.', 3, 'A1', '8 weeks', 'Beginner', 1),
('Intermediate Grammar', 'Master intermediate level English grammar concepts.', 3, 'B1', '10 weeks', 'Intermediate', 1),
('Advanced Conversation', 'Improve your speaking skills with advanced conversation topics.', 4, 'C1', '12 weeks', 'Advanced', 1),
('Business English', 'Learn essential English skills for professional environments.', 4, 'B2', '6 weeks', 'Intermediate', 0),
('IELTS Preparation', 'Comprehensive preparation for the IELTS exam.', 3, 'B2', '12 weeks', 'Advanced', 0);

-- A1 Level Questions (30 questions)
INSERT INTO level_test_questions (question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty_level) VALUES
-- Basic Grammar (10 questions)
('What ___ your name?', 'is', 'are', 'am', 'be', 'A', 'A1'),
('She ___ a student.', 'is', 'are', 'am', 'be', 'A', 'A1'),
('___ you like coffee?', 'Do', 'Does', 'Are', 'Is', 'A', 'A1'),
('How ___ oranges do you want?', 'many', 'much', 'long', 'often', 'A', 'A1'),
('Where ___ you from?', 'are', 'is', 'am', 'be', 'A', 'A1'),
('I ___ from Spain.', 'am', 'is', 'are', 'be', 'A', 'A1'),
('___ she like music?', 'Does', 'Do', 'Is', 'Are', 'A', 'A1'),
('They ___ students.', 'are', 'is', 'am', 'be', 'A', 'A1'),
('This is ___ umbrella.', 'an', 'a', 'the', '-', 'A', 'A1'),
('We ___ breakfast at 8 AM.', 'have', 'has', 'had', 'having', 'A', 'A1'),

-- Vocabulary (10 questions)
('What color is a banana?', 'yellow', 'red', 'blue', 'green', 'A', 'A1'),
('Which is a pet?', 'dog', 'lion', 'elephant', 'tiger', 'A', 'A1'),
('What do you wear on your feet?', 'shoes', 'hat', 'shirt', 'pants', 'A', 'A1'),
('Which is a fruit?', 'apple', 'carrot', 'potato', 'onion', 'A', 'A1'),
('What do you drink?', 'water', 'bread', 'rice', 'meat', 'A', 'A1'),
('Which room do you sleep in?', 'bedroom', 'kitchen', 'bathroom', 'living room', 'A', 'A1'),
('What do you use to write?', 'pen', 'plate', 'cup', 'fork', 'A', 'A1'),
('Which is a day of the week?', 'Monday', 'January', 'Summer', 'Morning', 'A', 'A1'),
('What do you use to tell time?', 'clock', 'book', 'phone', 'computer', 'A', 'A1'),
('Which is a number?', 'seven', 'blue', 'big', 'fast', 'A', 'A1'),

-- Simple Present & Present Continuous (10 questions)
('I ___ TV every evening.', 'watch', 'watches', 'watching', 'watched', 'A', 'A1'),
('She ___ to music now.', 'is listening', 'listen', 'listens', 'listening', 'A', 'A1'),
('They ___ football on Sundays.', 'play', 'plays', 'playing', 'is play', 'A', 'A1'),
('He ___ breakfast at the moment.', 'is having', 'have', 'has', 'having', 'A', 'A1'),
('We ___ English in class.', 'study', 'studies', 'studying', 'is study', 'A', 'A1'),
('The sun ___ in the east.', 'rises', 'rise', 'rising', 'is rise', 'A', 'A1'),
('I ___ my homework right now.', 'am doing', 'do', 'does', 'doing', 'A', 'A1'),
('She ___ coffee every morning.', 'drinks', 'drink', 'drinking', 'is drink', 'A', 'A1'),
('They ___ in the garden now.', 'are working', 'work', 'works', 'working', 'A', 'A1'),
('He ___ to school by bus.', 'goes', 'go', 'going', 'is go', 'A', 'A1'),

-- A2 Level Questions (30 questions)
-- Past Simple & Future (10 questions)
('I ___ to the cinema yesterday.', 'went', 'go', 'gone', 'going', 'A', 'A2'),
('She ___ her homework tomorrow.', 'will do', 'does', 'did', 'doing', 'A', 'A2'),
('They ___ tennis last weekend.', 'played', 'play', 'plays', 'playing', 'A', 'A2'),
('He ___ to London next month.', 'is going', 'goes', 'went', 'gone', 'A', 'A2'),
('We ___ a great time at the party.', 'had', 'have', 'has', 'having', 'A', 'A2'),
('I ___ you tomorrow at 5 PM.', 'will meet', 'meet', 'met', 'meeting', 'A', 'A2'),
('She ___ her keys this morning.', 'lost', 'lose', 'loses', 'losing', 'A', 'A2'),
('They ___ a new car next year.', 'will buy', 'buy', 'bought', 'buying', 'A', 'A2'),
('He ___ the exam last week.', 'passed', 'pass', 'passes', 'passing', 'A', 'A2'),
('We ___ to the beach tomorrow.', 'are going', 'go', 'went', 'gone', 'A', 'A2'),

-- Comparative & Superlative (10 questions)
('This book is ___ than that one.', 'better', 'good', 'best', 'more good', 'A', 'A2'),
('She is the ___ student in class.', 'best', 'better', 'good', 'more good', 'A', 'A2'),
('My car is ___ expensive than yours.', 'more', 'most', 'expensiver', 'expensivest', 'A', 'A2'),
('This is the ___ day of my life.', 'worst', 'worse', 'bad', 'more bad', 'A', 'A2'),
('He runs ___ than his brother.', 'faster', 'fast', 'fastest', 'more fast', 'A', 'A2'),
('This building is the ___ in the city.', 'tallest', 'taller', 'tall', 'more tall', 'A', 'A2'),
('Your phone is ___ than mine.', 'newer', 'new', 'newest', 'more new', 'A', 'A2'),
('She is the ___ person I know.', 'kindest', 'kinder', 'kind', 'more kind', 'A', 'A2'),
('This problem is ___ than the last one.', 'easier', 'easy', 'easiest', 'more easy', 'A', 'A2'),
('He is the ___ player on the team.', 'strongest', 'stronger', 'strong', 'more strong', 'A', 'A2'),

-- Modal Verbs & Prepositions (10 questions)
('You ___ smoke in the hospital.', 'must not', 'not must', 'don\'t must', 'mustn\'t to', 'A', 'A2'),
('She ___ speak three languages.', 'can', 'cans', 'could to', 'can to', 'A', 'A2'),
('We ___ leave now or we\'ll be late.', 'should', 'should to', 'must to', 'would to', 'A', 'A2'),
('The book is ___ the table.', 'on', 'in', 'at', 'to', 'A', 'A2'),
('I\'ll meet you ___ the station.', 'at', 'on', 'in', 'to', 'A', 'A2'),
('He lives ___ London.', 'in', 'at', 'on', 'to', 'A', 'A2'),
('The cat is sleeping ___ the bed.', 'under', 'above', 'at', 'to', 'A', 'A2'),
('She arrived ___ time for the meeting.', 'on', 'in', 'at', 'to', 'A', 'A2'),
('We\'re going ___ holiday next week.', 'on', 'in', 'at', 'to', 'A', 'A2'),
('The picture is hanging ___ the wall.', 'on', 'in', 'at', 'to', 'A', 'A2');

INSERT INTO `level_test_questions` (`question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `difficulty_level`) VALUES
('What ___ your name?', 'is', 'are', 'am', 'be', 'A', 'A1'),
('How ___ you?', 'is', 'are', 'am', 'be', 'B', 'A1'),
('She ___ a student.', 'is', 'are', 'am', 'be', 'A', 'A1'),
('___ you like coffee?', 'Do', 'Does', 'Are', 'Is', 'A', 'A1'),
('Where ___ you from?', 'is', 'are', 'am', 'be', 'B', 'A1'),
('I ___ hungry.', 'is', 'are', 'am', 'be', 'C', 'A1'),
('They ___ playing football.', 'is', 'are', 'am', 'be', 'B', 'A1'),
('This ___ my book.', 'is', 'are', 'am', 'be', 'A', 'A1'),
('What time ___ it?', 'is', 'are', 'am', 'be', 'A', 'A1'),
('We ___ friends.', 'is', 'are', 'am', 'be', 'B', 'A1');

INSERT INTO `level_test_questions` (`question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `difficulty_level`) VALUES
('He ___ watching TV right now.', 'is', 'are', 'be', 'been', 'A', 'A2'),
('I ___ to the cinema last week.', 'go', 'went', 'gone', 'going', 'B', 'A2'),
('Have you ___ been to Paris?', 'ever', 'never', 'already', 'yet', 'A', 'A2'),
('She ___ up at 7 AM every day.', 'wake', 'wakes', 'waking', 'waked', 'B', 'A2'),
('They ___ their homework yesterday.', 'do', 'did', 'done', 'doing', 'B', 'A2'),
('I ___ reading this book since morning.', 'am', 'have been', 'was', 'will be', 'B', 'A2'),
('He ___ his keys. He can\'t find them.', 'lost', 'has lost', 'loses', 'losing', 'B', 'A2'),
('What ___ you doing tomorrow?', 'is', 'are', 'am', 'be', 'B', 'A2'),
('She ___ tired after work.', 'is', 'are', 'am', 'be', 'A', 'A2'),
('We ___ never seen snow before.', 'have', 'had', 'has', 'having', 'A', 'A2');

INSERT INTO `level_test_questions` (`question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `difficulty_level`) VALUES
('If I ___ rich, I would travel the world.', 'am', 'was', 'were', 'be', 'C', 'B1'),
('He asked me what I ___ doing.', 'am', 'was', 'were', 'be', 'B', 'B1'),
('I wish I ___ speak better English.', 'can', 'could', 'would', 'will', 'B', 'B1'),
('She ___ in London for five years now.', 'lives', 'lived', 'has lived', 'living', 'C', 'B1'),
('By the time I got home, she ___ already left.', 'has', 'had', 'was', 'is', 'B', 'B1'),
('I ___ studying French for two years.', 'have been', 'am', 'was', 'will be', 'A', 'B1'),
('If it rains, we ___ stay at home.', 'will', 'would', 'shall', 'might', 'A', 'B1'),
('He ___ his bike when it started raining.', 'rides', 'rode', 'was riding', 'has ridden', 'C', 'B1'),
('She ___ her project by next week.', 'finishes', 'will finish', 'finished', 'finishing', 'B', 'B1'),
('They ___ us about the meeting earlier.', 'should tell', 'should have told', 'must tell', 'can tell', 'B', 'B1');

INSERT INTO `level_test_questions` (`question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `difficulty_level`) VALUES
('Had I known about the meeting, I ___ attended it.', 'would have', 'will have', 'had', 'have', 'A', 'B2'),
('The report ___ by Friday.', 'will complete', 'will be completed', 'completing', 'completed', 'B', 'B2'),
('Not only ___ the exam, but she also got the highest score.', 'she passed', 'did she pass', 'passed she', 'she did pass', 'B', 'B2'),
('Despite ___ hard, he failed the test.', 'study', 'studied', 'studying', 'studies', 'C', 'B2'),
('She ___ have told me about the party.', 'must', 'should', 'could', 'would', 'B', 'B2'),
('If he ___ earlier, he would have caught the train.', 'left', 'leaves', 'had left', 'has left', 'C', 'B2'),
('It is essential that he ___ on time.', 'arrive', 'arrives', 'will arrive', 'arrived', 'A', 'B2'),
('The book ___ by everyone in the class.', 'has read', 'has been read', 'is read', 'reads', 'B', 'B2'),
('No sooner ___ than it started raining.', 'we arrived', 'had we arrived', 'did we arrive', 'we had arrived', 'B', 'B2'),
('She ___ her mind about the job offer.', 'changed', 'changes', 'has changed', 'is changing', 'C', 'B2');

INSERT INTO `level_test_questions` (`question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `difficulty_level`) VALUES
('The novel, along with its sequels, ___ being adapted for television.', 'is', 'are', 'were', 'have', 'A', 'C1'),
('No sooner ___ the door than the phone rang.', 'I closed', 'had I closed', 'I had closed', 'did I close', 'B', 'C1'),
('Seldom ___ such dedication to work.', 'I see', 'I have seen', 'have I seen', 'I had seen', 'C', 'C1'),
('The manager, whose opinions ___ highly regarded, opposed the plan.', 'is', 'are', 'was', 'were', 'C', 'C1'),
('Were it not for his help, I ___ the project on time.', 'would not finish', 'would not have finished', 'had not finished', 'did not finish', 'B', 'C1'),
('So intricate ___ the plot that few readers fully understood it.', 'is', 'was', 'were', 'being', 'B', 'C1'),
('Under no circumstances ___ the confidential information be disclosed.', 'should', 'must', 'can', 'will', 'A', 'C1'),
('The more you practice, the ___ you become.', 'better', 'best', 'good', 'well', 'A', 'C1'),
('It was not until midnight that they ___ the solution.', 'find', 'found', 'had found', 'have found', 'B', 'C1'),
('Little ___ about the new regulations until now.', 'I knew', 'I have known', 'did I know', 'had I known', 'C', 'C1');

INSERT INTO `level_test_questions` (`question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `difficulty_level`) VALUES
('Were it not for his help, I ___ the project on time.', 'would not finish', 'would not have finished', 'had not finished', 'did not finish', 'B', 'C2'),
('So intricate ___ the plot that few readers fully understood it.', 'is', 'was', 'were', 'being', 'B', 'C2'),
('Under no circumstances ___ the confidential information be disclosed.', 'should', 'must', 'can', 'will', 'A', 'C2'),
('Had he ___ harder, he might have succeeded.', 'worked', 'works', 'working', 'work', 'A', 'C2'),
('Scarcely ___ when the storm began.', 'had we arrived', 'we had arrived', 'did we arrive', 'we arrived', 'A', 'C2'),
('Such was the complexity of the problem that ___ could solve it.', 'nobody', 'everybody', 'somebody', 'anybody', 'A', 'C2'),
('The teacher demanded that the students ___ their assignments on time.', 'submit', 'submits', 'submitted', 'submitting', 'A', 'C2'),
('Not until the sun set ___ the beauty of the landscape.', 'we appreciated', 'did we appreciate', 'we did appreciate', 'appreciated we', 'B', 'C2'),
('It is imperative that he ___ present at the meeting.', 'be', 'is', 'was', 'will be', 'A', 'C2'),
('Rarely ___ such a brilliant performance.', 'I have seen', 'have I seen', 'I saw', 'saw I', 'B', 'C2');

-- Insert course materials
INSERT INTO course_materials (course_id, title, description, content, order_number) VALUES
(1, 'Introduction to English', 'Basic concepts and greetings', 'Welcome to English for Beginners! In this lesson, we will learn basic greetings and introductions. We will cover:\n\n1. Common Greetings\n- Hello / Hi\n- Good morning / afternoon / evening\n- How are you?\n\n2. Introducing Yourself\n- My name is...\n- I am from...\n- Nice to meet you\n\n3. Basic Questions\n- What is your name?\n- Where are you from?\n- How are you?\n\nPractice these phrases and try to use them in your daily conversations!', 1),
(1, 'Basic Grammar', 'Learn fundamental grammar rules', 'Let\'s start with the basics of English grammar. In this lesson, we will explore:\n\n1. Subject Pronouns\n- I, You, He, She, It, We, They\n\n2. The Verb "To Be"\n- Present tense forms: am, is, are\n- Examples and usage\n\n3. Simple Sentences\n- Subject + Verb + Object structure\n- Making basic statements\n\nComplete the exercises at the end to practice these concepts!', 2),
(1, 'Simple Present Tense', 'Understanding and using simple present', 'The simple present tense is one of the most important tenses in English. Today we will learn:\n\n1. When to Use Simple Present\n- Daily routines\n- General facts\n- Habits\n\n2. Verb Forms\n- Regular verbs\n- Adding -s/-es for third person singular\n\n3. Common Time Expressions\n- Always, usually, sometimes, never\n- Every day, every week, etc.\n\nPractice using these concepts in the interactive exercises!', 3),
(2, 'Perfect Tenses', 'Understanding perfect tenses', 'Perfect tenses help us talk about completed actions. In this lesson:\n\n1. Present Perfect\n- Form: have/has + past participle\n- Uses and examples\n\n2. Past Perfect\n- Form: had + past participle\n- When to use it\n\n3. Common Signal Words\n- Already, yet, just\n- Ever, never\n\nTest your understanding with the quiz at the end!', 1),
(2, 'Conditionals', 'Learn about conditional sentences', 'Conditional sentences express hypothetical situations and their results. We will cover:\n\n1. Zero Conditional\n- Real/factual situations\n- If + present simple, present simple\n\n2. First Conditional\n- Possible future situations\n- If + present simple, will + infinitive\n\n3. Practice Exercises\n- Creating your own conditionals\n- Mixed practice activities\n\nComplete all exercises to master conditionals!', 2),
(3, 'Advanced Vocabulary', 'Expanding your vocabulary', 'Enhance your vocabulary with advanced words and phrases. Topics include:\n\n1. Academic Vocabulary\n- Common academic words\n- Using formal language\n\n2. Collocations\n- Word partnerships\n- Natural combinations\n\n3. Synonyms and Antonyms\n- Expanding your word choices\n- Adding variety to your speech\n\nPractice using these new words in context!', 1),
(3, 'Idiomatic Expressions', 'Common English idioms', 'English idioms add color to your language. In this lesson:\n\n1. Common Idioms\n- Meanings and origins\n- When to use them\n\n2. Business Idioms\n- Professional contexts\n- Formal vs informal use\n\n3. Practice Activities\n- Role-play scenarios\n- Writing exercises\n\nMaster these expressions to sound more natural!', 2),
(4, 'Business Communication', 'Professional email writing', 'Learn to write effective business emails. We cover:\n\n1. Email Structure\n- Subject lines\n- Greetings and closings\n\n2. Formal Language\n- Appropriate phrases\n- Professional tone\n\n3. Common Scenarios\n- Meeting requests\n- Follow-up emails\n\nPractice writing your own business emails!', 1),
(5, 'IELTS Writing Task 1', 'Academic graph description', 'Master IELTS Writing Task 1 with our comprehensive guide:\n\n1. Understanding Graphs\n- Types of visual information\n- Key features\n\n2. Language for Trends\n- Describing changes\n- Comparing data\n\n3. Practice Tests\n- Timed exercises\n- Sample answers\n\nPrepare effectively for your IELTS exam!', 1);

-- Insert course enrollments
INSERT INTO course_enrollments (user_id, course_id) VALUES
(2, 1),
(2, 2),
(2, 3);

-- Insert user progress
INSERT INTO user_progress (user_id, material_id, progress, last_accessed) VALUES
(2, 1, 100, NOW()),
(2, 2, 75, NOW()),
(2, 3, 25, NOW()),
(2, 4, 50, NOW());

-- Insert resource library items
INSERT INTO resource_library (title, description, file_path, file_type, proficiency_level, course_id) VALUES
-- A1 Level Resources (English for Beginners - Course ID 1)
('Beginner Vocabulary Flashcards', 'Essential vocabulary flashcards for beginners', '/uploads/resources/beginner_vocab_flashcards.pdf', 0, 'A1', 1),
('Basic Grammar Worksheet', 'Practice basic English grammar structures', '/uploads/resources/basic_grammar_worksheet.pdf', 2, 'A1', 1),
('English Alphabet Guide', 'Complete guide to English alphabet pronunciation', '/uploads/resources/alphabet_guide.pdf', 0, 'A1', 1),
('My First Words E-book', 'Interactive e-book for learning basic English vocabulary', '/uploads/resources/first_words_ebook.epub', 1, 'A1', 1),
('Numbers and Counting Worksheet', 'Learn to count and write numbers in English', '/uploads/resources/numbers_worksheet.pdf', 2, 'A1', 1),

-- A2 Level Resources (No specific A2 course, so NULL course_id)
('Elementary Conversation Guide', 'Simple conversation starters and phrases', '/uploads/resources/elementary_conversation.pdf', 0, 'A2', NULL),
('Present Tense Practice', 'Worksheet for practicing present simple and continuous tenses', '/uploads/resources/present_tense_practice.pdf', 2, 'A2', NULL),
('Basic Reading Comprehension', 'Short stories with comprehension questions', '/uploads/resources/basic_reading.pdf', 0, 'A2', NULL),
('Everyday English E-book', 'E-book with common phrases for daily situations', '/uploads/resources/everyday_english.epub', 1, 'A2', NULL),
('Travel Vocabulary Worksheet', 'Learn essential travel vocabulary and phrases', '/uploads/resources/travel_vocab_worksheet.pdf', 2, 'A2', NULL),

-- B1 Level Resources (Intermediate Grammar - Course ID 2)
('Intermediate Grammar Guide', 'Comprehensive guide to intermediate grammar concepts', '/uploads/resources/intermediate_grammar.pdf', 0, 'B1', 2),
('Past Tense Worksheet', 'Practice exercises for past simple and past continuous', '/uploads/resources/past_tense_worksheet.pdf', 2, 'B1', 2),
('Business English Basics', 'Introduction to business English vocabulary and phrases', '/uploads/resources/business_english_basics.pdf', 0, 'B1', 2),
('Short Stories Collection', 'E-book with intermediate-level short stories', '/uploads/resources/short_stories.epub', 1, 'B1', 2),
('Email Writing Templates', 'Templates and examples for writing effective emails', '/uploads/resources/email_templates.pdf', 2, 'B1', 2),

-- B2 Level Resources (Business English - Course ID 4 and IELTS Preparation - Course ID 5)
('Advanced Grammar Workbook', 'Detailed explanations and exercises for advanced grammar', '/uploads/resources/advanced_grammar.pdf', 0, 'B2', 4),
('Essay Writing Guide', 'Step-by-step guide to writing academic essays', '/uploads/resources/essay_writing.pdf', 0, 'B2', 5),
('Business Communication E-book', 'Comprehensive e-book on professional communication', '/uploads/resources/business_communication.epub', 1, 'B2', 4),
('Phrasal Verbs Worksheet', 'Practice exercises for common phrasal verbs', '/uploads/resources/phrasal_verbs_worksheet.pdf', 2, 'B2', 5),
('Presentation Skills Handbook', 'Guide to giving effective presentations in English', '/uploads/resources/presentation_skills.pdf', 0, 'B2', 4),

-- C1 Level Resources (Advanced Conversation - Course ID 3)
('Academic Writing Masterclass', 'Advanced techniques for academic writing', '/uploads/resources/academic_writing.pdf', 0, 'C1', 3),
('Literary Analysis Guide', 'Methods and examples for analyzing English literature', '/uploads/resources/literary_analysis.pdf', 0, 'C1', 3),
('Advanced Business English', 'E-book covering complex business English scenarios', '/uploads/resources/advanced_business.epub', 1, 'C1', 3),
('Debate and Argumentation', 'Worksheet on constructing logical arguments in English', '/uploads/resources/debate_worksheet.pdf', 2, 'C1', 3),
('Research Paper Template', 'Template and guidelines for writing research papers', '/uploads/resources/research_template.pdf', 0, 'C1', 3),

-- C2 Level Resources (No specific C2 course, so NULL course_id)
('Mastering Idiomatic Expressions', 'Comprehensive guide to English idioms and expressions', '/uploads/resources/idioms_guide.pdf', 0, 'C2', NULL),
('Advanced Stylistics', 'Analysis of style and rhetoric in English writing', '/uploads/resources/advanced_stylistics.pdf', 0, 'C2', NULL),
('Professional Writing E-book', 'E-book on writing for various professional contexts', '/uploads/resources/professional_writing.epub', 1, 'C2', NULL),
('Creative Writing Workshop', 'Advanced exercises for creative writing in English', '/uploads/resources/creative_writing.pdf', 2, 'C2', NULL),
('Academic Journal Templates', 'Templates for writing academic journal articles', '/uploads/resources/journal_templates.pdf', 0, 'C2', NULL);

-- Insert quizzes
INSERT INTO quizzes (title, description, difficulty_level) VALUES
-- Beginner Quizzes
('Basic English Grammar', 'Test your knowledge of fundamental English grammar concepts.', 0),
('Simple Present Tense', 'Practice questions about present simple tense usage.', 0),
('Basic Vocabulary', 'Test your knowledge of common English words and phrases.', 0),

-- Intermediate Quizzes
('Past Tenses', 'Test your understanding of past simple, past continuous, and past perfect.', 1),
('Conditional Sentences', 'Practice different types of conditional sentences.', 1),
('Phrasal Verbs', 'Test your knowledge of common phrasal verbs in English.', 1),

-- Advanced Quizzes
('Advanced Grammar', 'Challenge yourself with complex grammar structures.', 2),
('Academic Writing', 'Test your knowledge of academic writing conventions.', 2),
('Idiomatic Expressions', 'Test your understanding of advanced English idioms.', 2);

-- Insert quiz questions
-- Basic English Grammar (Beginner)
INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer) VALUES
(1, 'Which sentence is correct?', 0, '{"A": "I am student", "B": "I am a student", "C": "I am the student", "D": "I am students"}', 'B'),
(1, 'Choose the correct form of the verb: She ___ English every day.', 0, '{"A": "study", "B": "studies", "C": "studying", "D": "studied"}', 'B'),
(1, 'What is the correct article? ___ apple a day keeps the doctor away.', 0, '{"A": "A", "B": "An", "C": "The", "D": "No article"}', 'B'),
(1, 'Match the pronouns with their correct forms:', 1, '[{"left": "I", "right": "am"}, {"left": "You", "right": "are"}, {"left": "He", "right": "is"}, {"left": "They", "right": "are"}]', '[{"left": "I", "right": "am"}, {"left": "You", "right": "are"}, {"left": "He", "right": "is"}, {"left": "They", "right": "are"}]'),
(1, 'Correct the sentence: "I go to school yesterday."', 2, '{"sentence": "I go to school yesterday.", "correct": "I went to school yesterday."}', 'I went to school yesterday.'),
(1, 'Which is the correct plural form?', 0, '{"A": "childs", "B": "childrens", "C": "children", "D": "childes"}', 'C'),
(1, 'Match the adjectives with their opposites:', 1, '[{"left": "big", "right": "small"}, {"left": "hot", "right": "cold"}, {"left": "happy", "right": "sad"}, {"left": "new", "right": "old"}]', '[{"left": "big", "right": "small"}, {"left": "hot", "right": "cold"}, {"left": "happy", "right": "sad"}, {"left": "new", "right": "old"}]'),
(1, 'Choose the correct preposition: I will meet you ___ the library.', 0, '{"A": "in", "B": "at", "C": "on", "D": "by"}', 'B'),
(1, 'Correct the sentence: "She don\'t like coffee."', 2, '{"sentence": "She don\'t like coffee.", "correct": "She doesn\'t like coffee."}', 'She doesn\'t like coffee.'),
(1, 'Which sentence uses the correct possessive form?', 0, '{"A": "This is Johns book", "B": "This is John\'s book", "C": "This is Johns\' book", "D": "This is John book"}', 'B');

-- Simple Present Tense (Beginner)
INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer) VALUES
(2, 'Complete the sentence: She ___ to work by bus.', 0, '{"A": "go", "B": "goes", "C": "going", "D": "went"}', 'B'),
(2, 'Which sentence is in simple present tense?', 0, '{"A": "I am eating lunch", "B": "I eat lunch", "C": "I ate lunch", "D": "I will eat lunch"}', 'B'),
(2, 'Match the subjects with their correct verb forms:', 1, '[{"left": "I", "right": "work"}, {"left": "He", "right": "works"}, {"left": "They", "right": "work"}, {"left": "She", "right": "works"}]', '[{"left": "I", "right": "work"}, {"left": "He", "right": "works"}, {"left": "They", "right": "work"}, {"left": "She", "right": "works"}]'),
(2, 'Correct the sentence: "The sun rise in the east."', 2, '{"sentence": "The sun rise in the east.", "correct": "The sun rises in the east."}', 'The sun rises in the east.'),
(2, 'Choose the correct form: My sister ___ English.', 0, '{"A": "speak", "B": "speaks", "C": "speaking", "D": "spoke"}', 'B'),
(2, 'Match the time expressions with their usage:', 1, '[{"left": "every day", "right": "routine"}, {"left": "now", "right": "present continuous"}, {"left": "yesterday", "right": "past simple"}, {"left": "tomorrow", "right": "future"}]', '[{"left": "every day", "right": "routine"}, {"left": "now", "right": "present continuous"}, {"left": "yesterday", "right": "past simple"}, {"left": "tomorrow", "right": "future"}]'),
(2, 'Correct the sentence: "They plays football every weekend."', 2, '{"sentence": "They plays football every weekend.", "correct": "They play football every weekend."}', 'They play football every weekend.'),
(2, 'Which sentence shows a regular habit?', 0, '{"A": "I am reading a book", "B": "I read books", "C": "I read a book yesterday", "D": "I will read a book"}', 'B'),
(2, 'Match the verbs with their correct forms:', 1, '[{"left": "study", "right": "studies"}, {"left": "watch", "right": "watches"}, {"left": "go", "right": "goes"}, {"left": "do", "right": "does"}]', '[{"left": "study", "right": "studies"}, {"left": "watch", "right": "watches"}, {"left": "go", "right": "goes"}, {"left": "do", "right": "does"}]'),
(2, 'Correct the sentence: "The birds sings in the morning."', 2, '{"sentence": "The birds sings in the morning.", "correct": "The birds sing in the morning."}', 'The birds sing in the morning.');

-- Basic Vocabulary (Beginner)
INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer) VALUES
(3, 'What is the opposite of "happy"?', 0, '{"A": "sad", "B": "angry", "C": "tired", "D": "hungry"}', 'A'),
(3, 'Match the words with their meanings:', 1, '[{"left": "book", "right": "something to read"}, {"left": "pen", "right": "something to write with"}, {"left": "chair", "right": "something to sit on"}, {"left": "table", "right": "something to eat on"}]', '[{"left": "book", "right": "something to read"}, {"left": "pen", "right": "something to write with"}, {"left": "chair", "right": "something to sit on"}, {"left": "table", "right": "something to eat on"}]'),
(3, 'Which word means "a place to sleep"?', 0, '{"A": "bed", "B": "chair", "C": "table", "D": "desk"}', 'A'),
(3, 'Correct the sentence: "I am very hunger."', 2, '{"sentence": "I am very hunger.", "correct": "I am very hungry."}', 'I am very hungry.'),
(3, 'Match the colors with their names:', 1, '[{"left": "red", "right": "color of fire"}, {"left": "blue", "right": "color of sky"}, {"left": "green", "right": "color of grass"}, {"left": "yellow", "right": "color of sun"}]', '[{"left": "red", "right": "color of fire"}, {"left": "blue", "right": "color of sky"}, {"left": "green", "right": "color of grass"}, {"left": "yellow", "right": "color of sun"}]'),
(3, 'What is the correct word for "a place to study"?', 0, '{"A": "school", "B": "hospital", "C": "restaurant", "D": "shop"}', 'A'),
(3, 'Correct the sentence: "I have a new computer."', 2, '{"sentence": "I have a new computer.", "correct": "I have a new computer."}', 'I have a new computer.'),
(3, 'Match the numbers with their words:', 1, '[{"left": "1", "right": "one"}, {"left": "2", "right": "two"}, {"left": "3", "right": "three"}, {"left": "4", "right": "four"}]', '[{"left": "1", "right": "one"}, {"left": "2", "right": "two"}, {"left": "3", "right": "three"}, {"left": "4", "right": "four"}]'),
(3, 'Which word means "to move quickly on foot"?', 0, '{"A": "walk", "B": "run", "C": "jump", "D": "dance"}', 'B'),
(3, 'Correct the sentence: "The weather is very hot today."', 2, '{"sentence": "The weather is very hot today.", "correct": "The weather is very hot today."}', 'The weather is very hot today.');

-- Past Tenses (Intermediate)
INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer) VALUES
(4, 'Choose the correct past tense: I ___ my keys yesterday.', 0, '{"A": "lose", "B": "lost", "C": "losing", "D": "losed"}', 'B'),
(4, 'Match the tenses with their uses:', 1, '[{"left": "Past Simple", "right": "completed actions"}, {"left": "Past Continuous", "right": "ongoing actions"}, {"left": "Past Perfect", "right": "actions before other actions"}, {"left": "Present Perfect", "right": "recent past"}]', '[{"left": "Past Simple", "right": "completed actions"}, {"left": "Past Continuous", "right": "ongoing actions"}, {"left": "Past Perfect", "right": "actions before other actions"}, {"left": "Present Perfect", "right": "recent past"}]'),
(4, 'Correct the sentence: "I was study when she called."', 2, '{"sentence": "I was study when she called.", "correct": "I was studying when she called."}', 'I was studying when she called.'),
(4, 'Which sentence uses past perfect correctly?', 0, '{"A": "I had finished my homework before dinner", "B": "I have finished my homework before dinner", "C": "I finished my homework before dinner", "D": "I finish my homework before dinner"}', 'A'),
(4, 'Match the time expressions with their tenses:', 1, '[{"left": "yesterday", "right": "Past Simple"}, {"left": "while", "right": "Past Continuous"}, {"left": "before", "right": "Past Perfect"}, {"left": "just", "right": "Present Perfect"}]', '[{"left": "yesterday", "right": "Past Simple"}, {"left": "while", "right": "Past Continuous"}, {"left": "before", "right": "Past Perfect"}, {"left": "just", "right": "Present Perfect"}]'),
(4, 'Correct the sentence: "She had never been to Paris before."', 2, '{"sentence": "She had never been to Paris before.", "correct": "She had never been to Paris before."}', 'She had never been to Paris before.'),
(4, 'Choose the correct form: They ___ TV when I arrived.', 0, '{"A": "watched", "B": "were watching", "C": "had watched", "D": "watch"}', 'B'),
(4, 'Match the verbs with their past forms:', 1, '[{"left": "go", "right": "went"}, {"left": "take", "right": "took"}, {"left": "see", "right": "saw"}, {"left": "come", "right": "came"}]', '[{"left": "go", "right": "went"}, {"left": "take", "right": "took"}, {"left": "see", "right": "saw"}, {"left": "come", "right": "came"}]'),
(4, 'Correct the sentence: "I had already eat when she arrived."', 2, '{"sentence": "I had already eat when she arrived.", "correct": "I had already eaten when she arrived."}', 'I had already eaten when she arrived.'),
(4, 'Which sentence shows a past habit?', 0, '{"A": "I used to play tennis", "B": "I was playing tennis", "C": "I had played tennis", "D": "I played tennis"}', 'A');

-- Conditional Sentences (Intermediate)
INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer) VALUES
(5, 'Complete the first conditional: If it rains tomorrow, I ___ at home.', 0, '{"A": "stay", "B": "will stay", "C": "would stay", "D": "stayed"}', 'B'),
(5, 'Match the conditionals with their uses:', 1, '[{"left": "Zero", "right": "general truths"}, {"left": "First", "right": "possible future"}, {"left": "Second", "right": "unreal present"}, {"left": "Third", "right": "unreal past"}]', '[{"left": "Zero", "right": "general truths"}, {"left": "First", "right": "possible future"}, {"left": "Second", "right": "unreal present"}, {"left": "Third", "right": "unreal past"}]'),
(5, 'Correct the sentence: "If I would be rich, I would travel the world."', 2, '{"sentence": "If I would be rich, I would travel the world.", "correct": "If I were rich, I would travel the world."}', 'If I were rich, I would travel the world.'),
(5, 'Which is a zero conditional?', 0, '{"A": "If water reaches 100°C, it boils", "B": "If it rains, I will stay home", "C": "If I were you, I would study", "D": "If I had known, I would have told you"}', 'A'),
(5, 'Match the if-clauses with their main clauses:', 1, '[{"left": "If it rains", "right": "I will stay home"}, {"left": "If I were rich", "right": "I would buy a house"}, {"left": "If I had studied", "right": "I would have passed"}, {"left": "If you heat water", "right": "it boils"}]', '[{"left": "If it rains", "right": "I will stay home"}, {"left": "If I were rich", "right": "I would buy a house"}, {"left": "If I had studied", "right": "I would have passed"}, {"left": "If you heat water", "right": "it boils"}]'),
(5, 'Correct the sentence: "If I had known about the meeting, I would have attended it."', 2, '{"sentence": "If I had known about the meeting, I would have attended it.", "correct": "If I had known about the meeting, I would have attended it."}', 'If I had known about the meeting, I would have attended it.'),
(5, 'Choose the correct form: If I ___ you, I would study harder.', 0, '{"A": "am", "B": "was", "C": "were", "D": "be"}', 'C'),
(5, 'Match the conditionals with their examples:', 1, '[{"left": "Zero", "right": "If you heat water, it boils"}, {"left": "First", "right": "If it rains, I will stay home"}, {"left": "Second", "right": "If I were rich, I would travel"}, {"left": "Third", "right": "If I had known, I would have told you"}]', '[{"left": "Zero", "right": "If you heat water, it boils"}, {"left": "First", "right": "If it rains, I will stay home"}, {"left": "Second", "right": "If I were rich, I would travel"}, {"left": "Third", "right": "If I had known, I would have told you"}]'),
(5, 'Correct the sentence: "If I will see him, I will tell him."', 2, '{"sentence": "If I will see him, I will tell him.", "correct": "If I see him, I will tell him."}', 'If I see him, I will tell him.'),
(5, 'Which sentence shows a third conditional?', 0, '{"A": "If I had known, I would have told you", "B": "If I know, I will tell you", "C": "If I knew, I would tell you", "D": "If I know, I tell you"}', 'A');

-- Phrasal Verbs (Intermediate)
INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer) VALUES
(6, 'What does "give up" mean?', 0, '{"A": "to stop doing something", "B": "to start something", "C": "to continue something", "D": "to finish something"}', 'A'),
(6, 'Match the phrasal verbs with their meanings:', 1, '[{"left": "give up", "right": "stop doing"}, {"left": "look up", "right": "search for"}, {"left": "put off", "right": "postpone"}, {"left": "take off", "right": "remove"}]', '[{"left": "give up", "right": "stop doing"}, {"left": "look up", "right": "search for"}, {"left": "put off", "right": "postpone"}, {"left": "take off", "right": "remove"}]'),
(6, 'Correct the sentence: "I need to look up this word in the dictionary."', 2, '{"sentence": "I need to look up this word in the dictionary.", "correct": "I need to look up this word in the dictionary."}', 'I need to look up this word in the dictionary.'),
(6, 'Which phrasal verb means "to start"?', 0, '{"A": "set up", "B": "set down", "C": "set off", "D": "set in"}', 'A'),
(6, 'Match the phrasal verbs with their examples:', 1, '[{"left": "put off", "right": "I put off the meeting"}, {"left": "take off", "right": "The plane takes off"}, {"left": "look up", "right": "I look up the word"}, {"left": "give up", "right": "I give up smoking"}]', '[{"left": "put off", "right": "I put off the meeting"}, {"left": "take off", "right": "The plane takes off"}, {"left": "look up", "right": "I look up the word"}, {"left": "give up", "right": "I give up smoking"}]'),
(6, 'Correct the sentence: "I need to put off the meeting until tomorrow."', 2, '{"sentence": "I need to put off the meeting until tomorrow.", "correct": "I need to put off the meeting until tomorrow."}', 'I need to put off the meeting until tomorrow.'),
(6, 'Choose the correct phrasal verb: I need to ___ this task.', 0, '{"A": "carry out", "B": "carry in", "C": "carry off", "D": "carry on"}', 'A'),
(6, 'Match the phrasal verbs with their contexts:', 1, '[{"left": "set up", "right": "business"}, {"left": "take off", "right": "airplane"}, {"left": "look up", "right": "dictionary"}, {"left": "give up", "right": "bad habit"}]', '[{"left": "set up", "right": "business"}, {"left": "take off", "right": "airplane"}, {"left": "look up", "right": "dictionary"}, {"left": "give up", "right": "bad habit"}]'),
(6, 'Correct the sentence: "The plane takes off at 10 AM."', 2, '{"sentence": "The plane takes off at 10 AM.", "correct": "The plane takes off at 10 AM."}', 'The plane takes off at 10 AM.'),
(6, 'Which phrasal verb means "to continue"?', 0, '{"A": "carry on", "B": "carry out", "C": "carry off", "D": "carry in"}', 'A');

-- Advanced Grammar (Advanced)
INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer) VALUES
(7, 'Choose the correct form: The committee ___ divided on this issue.', 0, '{"A": "is", "B": "are", "C": "were", "D": "have"}', 'A'),
(7, 'Match the advanced structures with their uses:', 1, '[{"left": "Inversion", "right": "emphasis"}, {"left": "Cleft sentences", "right": "focus"}, {"left": "Ellipsis", "right": "conciseness"}, {"left": "Fronting", "right": "emphasis"}]', '[{"left": "Inversion", "right": "emphasis"}, {"left": "Cleft sentences", "right": "focus"}, {"left": "Ellipsis", "right": "conciseness"}, {"left": "Fronting", "right": "emphasis"}]'),
(7, 'Correct the sentence: "Not only did he pass the exam, but also got the highest score."', 2, '{"sentence": "Not only did he pass the exam, but also got the highest score.", "correct": "Not only did he pass the exam, but he also got the highest score."}', 'Not only did he pass the exam, but he also got the highest score.'),
(7, 'Which sentence uses inversion correctly?', 0, '{"A": "Never have I seen such beauty", "B": "Never I have seen such beauty", "C": "Never have seen I such beauty", "D": "Never I seen have such beauty"}', 'A'),
(7, 'Match the advanced structures with their examples:', 1, '[{"left": "Cleft sentence", "right": "What I need is a break"}, {"left": "Inversion", "right": "Hardly had I arrived"}, {"left": "Ellipsis", "right": "I can and will"}, {"left": "Fronting", "right": "This I cannot accept"}]', '[{"left": "Cleft sentence", "right": "What I need is a break"}, {"left": "Inversion", "right": "Hardly had I arrived"}, {"left": "Ellipsis", "right": "I can and will"}, {"left": "Fronting", "right": "This I cannot accept"}]'),
(7, 'Correct the sentence: "It was not until midnight that they found the solution."', 2, '{"sentence": "It was not until midnight that they found the solution.", "correct": "It was not until midnight that they found the solution."}', 'It was not until midnight that they found the solution.'),
(7, 'Choose the correct form: The number of students ___ increased.', 0, '{"A": "has", "B": "have", "C": "is", "D": "are"}', 'A'),
(7, 'Match the advanced structures with their contexts:', 1, '[{"left": "Cleft sentences", "right": "emphasis"}, {"left": "Inversion", "right": "formal writing"}, {"left": "Ellipsis", "right": "conversation"}, {"left": "Fronting", "right": "emphasis"}]', '[{"left": "Cleft sentences", "right": "emphasis"}, {"left": "Inversion", "right": "formal writing"}, {"left": "Ellipsis", "right": "conversation"}, {"left": "Fronting", "right": "emphasis"}]'),
(7, 'Correct the sentence: "Seldom have I seen such dedication."', 2, '{"sentence": "Seldom have I seen such dedication.", "correct": "Seldom have I seen such dedication."}', 'Seldom have I seen such dedication.'),
(7, 'Which sentence uses ellipsis correctly?', 0, '{"A": "I can and will help", "B": "I can help and will help", "C": "I can help and will"}', 'A');

-- Academic Writing (Advanced)
INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer) VALUES
(8, 'Which is the correct academic style?', 0, '{"A": "The research shows that...", "B": "The research proves that...", "C": "The research definitely shows that...", "D": "The research absolutely proves that..."}', 'A'),
(8, 'Match the academic phrases with their uses:', 1, '[{"left": "Furthermore", "right": "addition"}, {"left": "However", "right": "contrast"}, {"left": "Therefore", "right": "conclusion"}, {"left": "Moreover", "right": "addition"}]', '[{"left": "Furthermore", "right": "addition"}, {"left": "However", "right": "contrast"}, {"left": "Therefore", "right": "conclusion"}, {"left": "Moreover", "right": "addition"}]'),
(8, 'Correct the sentence: "The data shows that climate change is definitely getting worse."', 2, '{"sentence": "The data shows that climate change is definitely getting worse.", "correct": "The data suggests that climate change is increasing."}', 'The data suggests that climate change is increasing.'),
(8, 'Which sentence is more academic?', 0, '{"A": "The study found that...", "B": "The study discovered that...", "C": "The study figured out that...", "D": "The study worked out that..."}', 'A'),
(8, 'Match the academic words with their formal equivalents:', 1, '[{"left": "big", "right": "significant"}, {"left": "good", "right": "beneficial"}, {"left": "bad", "right": "detrimental"}, {"left": "show", "right": "demonstrate"}]', '[{"left": "big", "right": "significant"}, {"left": "good", "right": "beneficial"}, {"left": "bad", "right": "detrimental"}, {"left": "show", "right": "demonstrate"}]'),
(8, 'Correct the sentence: "The researchers found out that the treatment works."', 2, '{"sentence": "The researchers found out that the treatment works.", "correct": "The researchers determined that the treatment is effective."}', 'The researchers determined that the treatment is effective.'),
(8, 'Choose the correct academic word: The results ___ the hypothesis.', 0, '{"A": "support", "B": "back up", "C": "prove", "D": "show"}', 'A'),
(8, 'Match the academic structures with their purposes:', 1, '[{"left": "Literature review", "right": "background"}, {"left": "Methodology", "right": "procedure"}, {"left": "Results", "right": "findings"}, {"left": "Discussion", "right": "interpretation"}]', '[{"left": "Literature review", "right": "background"}, {"left": "Methodology", "right": "procedure"}, {"left": "Results", "right": "findings"}, {"left": "Discussion", "right": "interpretation"}]'),
(8, 'Correct the sentence: "The experiment worked really well."', 2, '{"sentence": "The experiment worked really well.", "correct": "The experiment was successful."}', 'The experiment was successful.'),
(8, 'Which sentence uses hedging correctly?', 0, '{"A": "The results suggest that...", "B": "The results prove that...", "C": "The results definitely show that...", "D": "The results absolutely prove that..."}', 'A');

-- Idiomatic Expressions (Advanced)
INSERT INTO quiz_questions (quiz_id, question_text, question_type, options, correct_answer) VALUES
(9, 'What does "hit the nail on the head" mean?', 0, '{"A": "to be exactly right", "B": "to make a mistake", "C": "to be confused", "D": "to be wrong"}', 'A'),
(9, 'Match the idioms with their meanings:', 1, '[{"left": "hit the nail on the head", "right": "be exactly right"}, {"left": "piece of cake", "right": "very easy"}, {"left": "raining cats and dogs", "right": "heavy rain"}, {"left": "break a leg", "right": "good luck"}]', '[{"left": "hit the nail on the head", "right": "be exactly right"}, {"left": "piece of cake", "right": "very easy"}, {"left": "raining cats and dogs", "right": "heavy rain"}, {"left": "break a leg", "right": "good luck"}]'),
(9, 'Correct the sentence: "It\'s raining cats and dogs outside."', 2, '{"sentence": "It\'s raining cats and dogs outside.", "correct": "It\'s raining cats and dogs outside."}', 'It\'s raining cats and dogs outside.'),
(9, 'Which idiom means "very easy"?', 0, '{"A": "piece of cake", "B": "hard as nails", "C": "tough cookie", "D": "hard nut to crack"}', 'A'),
(9, 'Match the idioms with their contexts:', 1, '[{"left": "break a leg", "right": "before performance"}, {"left": "piece of cake", "right": "easy task"}, {"left": "raining cats and dogs", "right": "bad weather"}, {"left": "hit the nail", "right": "correct answer"}]', '[{"left": "break a leg", "right": "before performance"}, {"left": "piece of cake", "right": "easy task"}, {"left": "raining cats and dogs", "right": "bad weather"}, {"left": "hit the nail", "right": "correct answer"}]'),
(9, 'Correct the sentence: "The exam was a piece of cake."', 2, '{"sentence": "The exam was a piece of cake.", "correct": "The exam was a piece of cake."}', 'The exam was a piece of cake.'),
(9, 'Choose the correct idiom: The project was ___.', 0, '{"A": "a piece of cake", "B": "a hard nut to crack", "C": "raining cats and dogs", "D": "break a leg"}', 'A'),
(9, 'Match the idioms with their examples:', 1, '[{"left": "break a leg", "right": "Good luck on your exam!"}, {"left": "piece of cake", "right": "That was easy!"}, {"left": "raining cats", "right": "It\'s pouring!"}, {"left": "hit the nail", "right": "You\'re right!"}]', '[{"left": "break a leg", "right": "Good luck on your exam!"}, {"left": "piece of cake", "right": "That was easy!"}, {"left": "raining cats", "right": "It\'s pouring!"}, {"left": "hit the nail", "right": "You\'re right!"}]'),
(9, 'Correct the sentence: "Good luck! Break a leg!"', 2, '{"sentence": "Good luck! Break a leg!", "correct": "Good luck! Break a leg!"}', 'Good luck! Break a leg!'),
(9, 'Which idiom means "good luck"?', 0, '{"A": "break a leg", "B": "piece of cake", "C": "raining cats and dogs", "D": "hit the nail on the head"}', 'A');

-- Sample data for Wordscapes game
INSERT INTO wordscapes_levels (difficulty, given_letters, level_number) VALUES
(1, 'PEACHES', 1),
(2, 'BREADS', 2),
(3, 'GARDEN', 3);

INSERT INTO wordscapes_words (level_id, word) VALUES
-- Level 1 words
(1, 'PEACH'),
(1, 'Peache'),
(1, 'Peaches'),
(1, 'PACE'),
(1, 'PEACE'),
-- Level 2 words
(2, 'BREAD'),
(2, 'BREADS'),
(2, 'READ'),
-- Level 3 words
(3, 'GAR'),
(3, 'GARD'),
(3, 'GARDE'),
(3, 'GARDEN');
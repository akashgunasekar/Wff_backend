-- PRODUCTION SEED DATA (WFF Rudra Classic 2026)

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'WFF Tamil Nadu'),
('phone', '+91 9952922686');

INSERT INTO `announcements` (`message`, `link_text`, `link_url`, `status`) VALUES
('WFF RUDRA CLASSIC & NATURAL LEAGUE 2026 REGISTRATION OPEN', 'REGISTER NOW', '/events/wff-rudra-classic-2026', 1);

INSERT INTO `events` (`slug`, `event_name`, `event_date`, `venue`, `banner_image`, `status`, `description`) VALUES
('wff-rudra-classic-2026', 'WFF Rudra Classic / WFF Natural League Tamil Nadu 2026', '2026-09-20', 'Tamil Nadu Physical Education and Sports University, Melakottaiyur, Chennai 127, Tamil Nadu', '/assets/wff_hero_banner.png', 'upcoming', 'Registration: 8:00 AM – 10:00 AM. Show: 11:00 AM onwards.');

INSERT INTO `event_categories` (`event_id`, `name`, `entry_fee`, `availability`) VALUES
(1, 'Junior Bermuda — Men Physique — Under 23 Years', NULL, 'open'),
(1, 'Junior Body Building — Under 23 Years', NULL, 'open'),
(1, 'Denim Jeans Model — Single Category', NULL, 'open'),
(1, 'Senior Bermuda — Men Physique — Below 170 cm / Above 170 cm', NULL, 'open'),
(1, 'Senior Body Building — 60 kg / 65 kg / 70 kg / 75 kg', NULL, 'open');

INSERT INTO `officials` (`name`, `role`, `designation`, `photo`, `status`) VALUES
('N. Mohan Kumar (Mr India)', 'President, WFF Tamil Nadu', 'Secretary, WFF India', '', 1),
('Dhana Sekar', 'Organizer', 'President, WFF Chengalpattu', '', 1);

INSERT INTO `hero_slides` (`title`, `subtitle`, `image`, `status`) VALUES
('WFF RUDRA CLASSIC 2026', 'TAMIL NADU PHYSICAL EDUCATION AND SPORTS UNIVERSITY', '/assets/wff_hero_banner.png', 1);

<?php function read_env_file($file_path)
{
    $env_file = file_get_contents($file_path);
    $env_lines = explode("\n", $env_file);
    $env_data = [];

    foreach ($env_lines as $line) {
        $line_parts = explode('=', $line);
        if (count($line_parts) == 2) {
            $key = trim($line_parts[0]);
            $value = trim($line_parts[1]);
            $env_data[$key] = $value;
        }
    }

    return $env_data;
}
$copyright_year = date("Y");

$env_data = read_env_file('.env');

$business_name = $env_data['BUSINESS_NAME'] ?? 'PowerFit Gym';
$description = $env_data['DESCRIPTION'] ?? '';
$about_us = $env_data['ABOUT'] ?? '';
$country = $env_data['COUNTRY'] ?? '';
$city = $env_data['CITY'] ?? '';
$street = $env_data['STREET'] ?? '';
$hause_no = $env_data['HOUSE_NUMBER'] ?? '';
$capacity = $env_data['CAPACITY'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? 'EN';

$conn = new mysqli($env_data['DB_SERVER'], $env_data['DB_USERNAME'], $env_data['DB_PASSWORD'], $env_data['DB_NAME']);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get opening hours
$days = [];
$result = $conn->query("SELECT * FROM opening_hours ORDER BY day ASC");
while ($row = $result->fetch_assoc()) {
    $days[] = $row;
}

// Get current capacity
$sql = "SELECT COUNT(*) AS total FROM temp_loggeduser";
$result = $conn->query($sql);
$workoutpeoplenow = ($result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;

// Get trainers
$trainers = [];
$result = $conn->query("SELECT * FROM trainers LIMIT 6");
while ($row = $result->fetch_assoc()) {
    $trainers[] = $row;
}

// Get membership packages
$packages = [];
$result = $conn->query("SELECT * FROM tickets ORDER BY price ASC");
while ($row = $result->fetch_assoc()) {
    $packages[] = $row;
}

$dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $business_name; ?> - Transform Your Body, Transform Your Life</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="shortcut icon" href="assets/img/brand/favicon.png" type="image/x-icon">
    <style>
        :root {
            --primary: #F97316;
            --primary-dark: #EA580C;
            --secondary: #FB923C;
            --accent: #22C55E;
            --accent-dark: #16A34A;
            --background: #0F172A;
            --background-light: #1E293B;
            --foreground: #F8FAFC;
            --muted: #64748B;
            --border: #334155;
            --surface: #1E293B;
            --surface-hover: #334155;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-snap-type: y proximity;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background);
            color: var(--foreground);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--background);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            background: transparent;
        }

        .navbar.scrolled {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--foreground);
        }

        .nav-logo img {
            height: 45px;
        }

        .nav-logo span {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            color: var(--foreground);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: color 0.3s ease;
            position: relative;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-cta {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: var(--foreground);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(249, 115, 22, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--foreground);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(249, 115, 22, 0.4);
        }

        .btn-accent {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: var(--foreground);
        }

        .btn-accent:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.4);
        }

        .btn-lg {
            padding: 16px 40px;
            font-size: 1.1rem;
        }

        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--foreground);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .mobile-menu {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--background);
            z-index: 999;
            padding: 2rem;
            flex-direction: column;
            gap: 2rem;
        }

        .mobile-menu.active {
            display: flex;
        }

        .mobile-menu-close {
            align-self: flex-end;
            background: none;
            border: none;
            color: var(--foreground);
            font-size: 2rem;
            cursor: pointer;
        }

        .mobile-menu a {
            color: var(--foreground);
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 600;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            scroll-snap-align: start;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.8)),
                        url('assets/img/hero-bg.jpg') center/cover;
            z-index: -1;
        }

        .hero-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(249, 115, 22, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(34, 197, 94, 0.1) 0%, transparent 50%);
            z-index: -1;
        }

        .hero-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-text {
            animation: fadeInUp 1s ease;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(249, 115, 22, 0.15);
            border: 1px solid rgba(249, 115, 22, 0.3);
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1.5rem;
        }

        .hero-badge i {
            font-size: 1rem;
        }

        .hero h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }

        .hero h1 span {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.2rem;
            color: var(--muted);
            margin-bottom: 2rem;
            max-width: 500px;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-stats {
            display: flex;
            gap: 3rem;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }

        .stat-item h3 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-item p {
            font-size: 0.9rem;
            color: var(--muted);
            margin: 0;
        }

        .hero-visual {
            position: relative;
            animation: fadeInRight 1s ease 0.3s both;
        }

        .hero-image-container {
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }

        .hero-image-container img {
            width: 100%;
            height: auto;
            display: block;
        }

        .hero-floating-card {
            position: absolute;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: float 3s ease-in-out infinite;
        }

        .hero-floating-card.card-1 {
            bottom: 20%;
            left: -50px;
            animation-delay: 0s;
        }

        .hero-floating-card.card-2 {
            top: 20%;
            right: -30px;
            animation-delay: 1.5s;
        }

        .floating-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .floating-icon.orange {
            background: rgba(249, 115, 22, 0.2);
            color: var(--primary);
        }

        .floating-icon.green {
            background: rgba(34, 197, 94, 0.2);
            color: var(--accent);
        }

        .floating-text h4 {
            font-size: 0.9rem;
            font-weight: 700;
            margin: 0;
        }

        .floating-text p {
            font-size: 0.75rem;
            color: var(--muted);
            margin: 0;
        }

        /* Capacity Bar */
        .capacity-bar {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1rem 1.5rem;
            z-index: 100;
            min-width: 200px;
        }

        .capacity-bar h4 {
            font-size: 0.75rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .capacity-progress {
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .capacity-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--primary));
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        .capacity-text {
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Sections Common */
        section {
            padding: 6rem 2rem;
            scroll-snap-align: start;
        }

        .section-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 4rem;
        }

        .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(249, 115, 22, 0.15);
            border: 1px solid rgba(249, 115, 22, 0.3);
            padding: 6px 14px;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .section-header h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .section-header p {
            font-size: 1.1rem;
            color: var(--muted);
        }

        /* Features Section */
        .features {
            background: var(--background-light);
        }

        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border-color: var(--primary);
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
        }

        .feature-icon.orange {
            background: rgba(249, 115, 22, 0.15);
            color: var(--primary);
        }

        .feature-icon.green {
            background: rgba(34, 197, 94, 0.15);
            color: var(--accent);
        }

        .feature-icon.blue {
            background: rgba(59, 130, 246, 0.15);
            color: #3B82F6;
        }

        .feature-icon.purple {
            background: rgba(168, 85, 247, 0.15);
            color: #A855F7;
        }

        .feature-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .feature-card p {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.7;
        }

        /* Trainers Section */
        .trainers-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .trainer-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .trainer-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .trainer-image {
            height: 280px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .trainer-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .trainer-image .placeholder-icon {
            font-size: 4rem;
            color: rgba(255, 255, 255, 0.3);
        }

        .trainer-info {
            padding: 1.5rem;
        }

        .trainer-info h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .trainer-specialty {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .trainer-info p {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .trainer-social {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .trainer-social a {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--foreground);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .trainer-social a:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }

        /* Pricing Section */
        .pricing {
            background: var(--background-light);
        }

        .pricing-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .pricing-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.5rem;
            position: relative;
            transition: all 0.3s ease;
        }

        .pricing-card.featured {
            border-color: var(--primary);
            transform: scale(1.05);
        }

        .pricing-card.featured::before {
            content: 'MOST POPULAR';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 6px 20px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .pricing-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .pricing-card.featured:hover {
            transform: scale(1.05) translateY(-8px);
        }

        .pricing-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }

        .pricing-header h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .pricing-header .price {
            font-size: 3rem;
            font-weight: 900;
            color: var(--primary);
        }

        .pricing-header .price span {
            font-size: 1rem;
            color: var(--muted);
            font-weight: 400;
        }

        .pricing-features {
            list-style: none;
            margin-bottom: 2rem;
        }

        .pricing-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 0;
            color: var(--muted);
        }

        .pricing-features li i {
            color: var(--accent);
            font-size: 1.1rem;
        }

        .pricing-features li.disabled {
            opacity: 0.5;
        }

        .pricing-features li.disabled i {
            color: var(--muted);
        }

        .pricing-card .btn {
            width: 100%;
            justify-content: center;
        }

        /* Testimonials Section */
        .testimonials-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .testimonial-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
            position: relative;
        }

        .testimonial-card::before {
            content: '"';
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 5rem;
            font-weight: 900;
            color: rgba(249, 115, 22, 0.1);
            line-height: 1;
        }

        .testimonial-stars {
            display: flex;
            gap: 4px;
            margin-bottom: 1rem;
        }

        .testimonial-stars i {
            color: var(--primary);
            font-size: 1rem;
        }

        .testimonial-text {
            font-size: 1rem;
            line-height: 1.8;
            color: var(--foreground);
            margin-bottom: 1.5rem;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .testimonial-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }

        .testimonial-author-info h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .testimonial-author-info p {
            font-size: 0.85rem;
            color: var(--muted);
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"/></svg>') repeat;
            background-size: 50px;
            opacity: 0.5;
        }

        .cta-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .cta-content h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 900;
            margin-bottom: 1.5rem;
        }

        .cta-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-white {
            background: white;
            color: var(--primary);
        }

        .btn-white:hover {
            background: var(--foreground);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        /* Contact Section */
        .contact {
            background: var(--background-light);
        }

        .contact-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .contact-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .contact-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .contact-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: rgba(249, 115, 22, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: var(--primary);
            flex-shrink: 0;
        }

        .contact-item h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .contact-item p {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .hours-grid {
            display: grid;
            gap: 0.5rem;
        }

        .hour-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .hour-item:last-child {
            border-bottom: none;
        }

        .hour-item .day {
            font-weight: 600;
        }

        .hour-item .time {
            color: var(--primary);
        }

        .hour-item.closed .time {
            color: var(--accent);
        }

        /* Footer */
        footer {
            background: var(--background);
            padding: 4rem 2rem 2rem;
            border-top: 1px solid var(--border);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .footer-brand img {
            height: 50px;
            margin-bottom: 1rem;
        }

        .footer-brand p {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.7;
        }

        .footer-links h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--foreground);
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .footer-social {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .footer-social a {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--foreground);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-social a:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 0 auto;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--muted);
            font-size: 0.85rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero p {
                margin: 0 auto 2rem;
            }

            .hero-buttons {
                justify-content: center;
            }

            .hero-stats {
                justify-content: center;
            }

            .hero-visual {
                display: none;
            }

            .nav-links {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .capacity-bar {
                display: none;
            }
        }

        @media (max-width: 768px) {
            section {
                padding: 4rem 1.5rem;
            }

            .hero-stats {
                flex-direction: column;
                gap: 1.5rem;
                align-items: center;
            }

            .pricing-card.featured {
                transform: none;
            }

            .pricing-card.featured:hover {
                transform: translateY(-8px);
            }
        }

        @media (max-width: 480px) {
            .navbar {
                padding: 1rem;
            }

            .btn-lg {
                padding: 14px 28px;
                font-size: 1rem;
            }

            .hero h1 {
                font-size: 2rem;
            }
        }

        /* Reduced Motion */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <a href="/" class="nav-logo">
            <img src="assets/img/brand/logo.png" alt="<?php echo $business_name; ?> Logo">
            <span><?php echo $business_name; ?></span>
        </a>
        <ul class="nav-links">
            <li><a href="#features">Features</a></li>
            <li><a href="#trainers">Trainers</a></li>
            <li><a href="#pricing">Pricing</a></li>
            <li><a href="#testimonials">Reviews</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <div class="nav-cta">
            <a href="login/" class="btn btn-outline">Login</a>
            <a href="register/" class="btn btn-primary">Join Now</a>
        </div>
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="bi bi-list"></i>
        </button>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <button class="mobile-menu-close" onclick="toggleMobileMenu()">
            <i class="bi bi-x-lg"></i>
        </button>
        <a href="#features" onclick="toggleMobileMenu()">Features</a>
        <a href="#trainers" onclick="toggleMobileMenu()">Trainers</a>
        <a href="#pricing" onclick="toggleMobileMenu()">Pricing</a>
        <a href="#testimonials" onclick="toggleMobileMenu()">Reviews</a>
        <a href="#contact" onclick="toggleMobileMenu()">Contact</a>
        <a href="login/">Login</a>
        <a href="register/" style="color: var(--primary);">Join Now</a>
    </div>

    <!-- Hero Section -->
    <section class="hero" id="hero">
        <div class="hero-bg"></div>
        <div class="hero-pattern"></div>
        <div class="hero-content">
            <div class="hero-text">
                <div class="hero-badge">
                    <i class="bi bi-lightning-charge-fill"></i>
                    #1 Gym in Addis Ababa
                </div>
                <h1>Transform Your Body, <span>Transform Your Life</span></h1>
                <p>Join PowerFit Gym and unlock your full potential with state-of-the-art equipment, expert trainers, and a supportive community.</p>
                <div class="hero-buttons">
                    <a href="register/" class="btn btn-primary btn-lg">
                        <i class="bi bi-rocket-takeoff-fill"></i>
                        Start Your Journey
                    </a>
                    <a href="#pricing" class="btn btn-outline btn-lg">
                        <i class="bi bi-play-circle"></i>
                        View Plans
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <h3>500+</h3>
                        <p>Active Members</p>
                    </div>
                    <div class="stat-item">
                        <h3>15+</h3>
                        <p>Expert Trainers</p>
                    </div>
                    <div class="stat-item">
                        <h3>50+</h3>
                        <p>Equipment</p>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-image-container">
                    <img src="assets/img/hero-bg.jpg" alt="PowerFit Gym Interior" onerror="this.style.display='none'">
                </div>
                <div class="hero-floating-card card-1">
                    <div class="floating-icon orange">
                        <i class="bi bi-fire"></i>
                    </div>
                    <div class="floating-text">
                        <h4>850 cal burned</h4>
                        <p>Average per session</p>
                    </div>
                </div>
                <div class="hero-floating-card card-2">
                    <div class="floating-icon green">
                        <i class="bi bi-trophy-fill"></i>
                    </div>
                    <div class="floating-text">
                        <h4>98% Satisfaction</h4>
                        <p>Member happiness rate</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Capacity Bar -->
    <div class="capacity-bar">
        <h4>Current Capacity</h4>
        <div class="capacity-progress">
            <div class="capacity-fill" style="width: <?php echo min(($workoutpeoplenow / max($capacity, 1)) * 100, 100); ?>%"></div>
        </div>
        <p class="capacity-text">
            <i class="bi bi-people-fill"></i>
            <?php echo $workoutpeoplenow; ?> / <?php echo $capacity; ?> people
        </p>
    </div>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-header animate-on-scroll">
            <div class="section-badge">
                <i class="bi bi-gear-fill"></i>
                Why Choose Us
            </div>
            <h2>Everything You Need to Succeed</h2>
            <p>From cutting-edge equipment to personalized training plans, we provide all the tools for your fitness journey.</p>
        </div>
        <div class="features-grid">
            <div class="feature-card animate-on-scroll">
                <div class="feature-icon orange">
                    <i class="bi bi-router-fill"></i>
                </div>
                <h3>Modern Equipment</h3>
                <p>State-of-the-art machines and free weights from leading brands, maintained to the highest standards.</p>
            </div>
            <div class="feature-card animate-on-scroll">
                <div class="feature-icon green">
                    <i class="bi bi-person-workspace"></i>
                </div>
                <h3>Expert Trainers</h3>
                <p>Certified professionals who create personalized workout plans tailored to your goals.</p>
            </div>
            <div class="feature-card animate-on-scroll">
                <div class="feature-icon blue">
                    <i class="bi bi-calendar-event-fill"></i>
                </div>
                <h3>Group Classes</h3>
                <p>High-energy classes including HIIT, Yoga, Spin, and more to keep your workouts exciting.</p>
            </div>
            <div class="feature-card animate-on-scroll">
                <div class="feature-icon purple">
                    <i class="bi bi-phone-fill"></i>
                </div>
                <h3>Mobile App</h3>
                <p>Track your progress, book classes, and manage your membership from your smartphone.</p>
            </div>
            <div class="feature-card animate-on-scroll">
                <div class="feature-icon orange">
                    <i class="bi bi-shower"></i>
                </div>
                <h3>Premium Facilities</h3>
                <p>Clean locker rooms, showers, sauna, and all amenities for your comfort.</p>
            </div>
            <div class="feature-card animate-on-scroll">
                <div class="feature-icon green">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h3>Supportive Community</h3>
                <p>Join a motivating community of like-minded individuals who support each other.</p>
            </div>
        </div>
    </section>

    <!-- Trainers Section -->
    <section class="trainers" id="trainers">
        <div class="section-header animate-on-scroll">
            <div class="section-badge">
                <i class="bi bi-person-hearts"></i>
                Our Team
            </div>
            <h2>Meet Our Expert Trainers</h2>
            <p>Our certified professionals are dedicated to helping you achieve your fitness goals safely and effectively.</p>
        </div>
        <div class="trainers-grid">
            <?php if (!empty($trainers)): ?>
                <?php foreach ($trainers as $trainer): ?>
                    <div class="trainer-card animate-on-scroll">
                        <div class="trainer-image">
                            <?php if (!empty($trainer['profile_picture'])): ?>
                                <img src="assets/img/trainers/<?php echo $trainer['profile_picture']; ?>" alt="<?php echo htmlspecialchars($trainer['name']); ?>">
                            <?php else: ?>
                                <i class="bi bi-person-fill placeholder-icon"></i>
                            <?php endif; ?>
                        </div>
                        <div class="trainer-info">
                            <h3><?php echo htmlspecialchars($trainer['name'] ?? 'Trainer'); ?></h3>
                            <p class="trainer-specialty"><?php echo htmlspecialchars($trainer['specialty'] ?? 'Fitness Expert'); ?></p>
                            <p><?php echo htmlspecialchars($trainer['bio'] ?? 'Passionate about helping you reach your goals.'); ?></p>
                            <div class="trainer-social">
                                <a href="#"><i class="bi bi-instagram"></i></a>
                                <a href="#"><i class="bi bi-twitter-x"></i></a>
                                <a href="#"><i class="bi bi-linkedin"></i></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="trainer-card animate-on-scroll">
                    <div class="trainer-image">
                        <i class="bi bi-person-fill placeholder-icon"></i>
                    </div>
                    <div class="trainer-info">
                        <h3>Join Our Team</h3>
                        <p class="trainer-specialty">Fitness Expert</p>
                        <p>We're always looking for talented trainers to join our team.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing" id="pricing">
        <div class="section-header animate-on-scroll">
            <div class="section-badge">
                <i class="bi bi-tag-fill"></i>
                Pricing Plans
            </div>
            <h2>Flexible Plans for Every Goal</h2>
            <p>Choose the perfect membership plan that fits your lifestyle and budget.</p>
        </div>
        <div class="pricing-grid">
            <?php if (!empty($packages)): ?>
                <?php $i = 0; foreach ($packages as $package): ?>
                    <div class="pricing-card <?php echo ($i === 1) ? 'featured' : ''; ?> animate-on-scroll">
                        <div class="pricing-header">
                            <h3><?php echo htmlspecialchars($package['name']); ?></h3>
                            <div class="price">
                                $<?php echo number_format($package['price'], 0); ?>
                                <span>/<?php echo $package['expire_days'] > 0 ? $package['expire_days'] . ' days' : 'session'; ?></span>
                            </div>
                        </div>
                        <ul class="pricing-features">
                            <li><i class="bi bi-check-circle-fill"></i> Full gym access</li>
                            <li><i class="bi bi-check-circle-fill"></i> All equipment</li>
                            <li><i class="bi bi-check-circle-fill"></i> Locker room</li>
                            <?php if ($package['occasions'] > 5): ?>
                                <li><i class="bi bi-check-circle-fill"></i> Group classes</li>
                            <?php else: ?>
                                <li class="disabled"><i class="bi bi-dash-circle"></i> Group classes</li>
                            <?php endif; ?>
                            <?php if ($package['occasions'] > 20): ?>
                                <li><i class="bi bi-check-circle-fill"></i> Personal trainer</li>
                            <?php else: ?>
                                <li class="disabled"><i class="bi bi-dash-circle"></i> Personal trainer</li>
                            <?php endif; ?>
                        </ul>
                        <a href="register/" class="btn <?php echo ($i === 1) ? 'btn-primary' : 'btn-outline'; ?>">
                            Get Started
                        </a>
                    </div>
                <?php $i++; endforeach; ?>
            <?php else: ?>
                <div class="pricing-card animate-on-scroll">
                    <div class="pricing-header">
                        <h3>Day Pass</h3>
                        <div class="price">$5<span>/day</span></div>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="bi bi-check-circle-fill"></i> Full gym access</li>
                        <li><i class="bi bi-check-circle-fill"></i> All equipment</li>
                        <li><i class="bi bi-check-circle-fill"></i> Locker room</li>
                    </ul>
                    <a href="register/" class="btn btn-outline">Get Started</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <div class="section-header animate-on-scroll">
            <div class="section-badge">
                <i class="bi bi-star-fill"></i>
                Testimonials
            </div>
            <h2>What Our Members Say</h2>
            <p>Real stories from real people who transformed their lives with PowerFit Gym.</p>
        </div>
        <div class="testimonials-grid">
            <div class="testimonial-card animate-on-scroll">
                <div class="testimonial-stars">
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                </div>
                <p class="testimonial-text">"PowerFit Gym completely changed my life. I lost 30kg in 6 months with the help of their amazing trainers. The community here is incredibly supportive!"</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">S</div>
                    <div class="testimonial-author-info">
                        <h4>Sarah M.</h4>
                        <p>Member since 2023</p>
                    </div>
                </div>
            </div>
            <div class="testimonial-card animate-on-scroll">
                <div class="testimonial-stars">
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                </div>
                <p class="testimonial-text">"The equipment is top-notch and always well-maintained. I've been to many gyms, and PowerFit is by far the best in Addis Ababa. Highly recommended!"</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">A</div>
                    <div class="testimonial-author-info">
                        <h4>Abebe K.</h4>
                        <p>Member since 2022</p>
                    </div>
                </div>
            </div>
            <div class="testimonial-card animate-on-scroll">
                <div class="testimonial-stars">
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                </div>
                <p class="testimonial-text">"I love the group classes! They're fun, energetic, and always challenging. The trainers make sure everyone is included and motivated."</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">F</div>
                    <div class="testimonial-author-info">
                        <h4>Fatima A.</h4>
                        <p>Member since 2024</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-content animate-on-scroll">
            <h2>Ready to Start Your Transformation?</h2>
            <p>Join PowerFit Gym today and get your first week free. No contracts, no hidden fees.</p>
            <div class="cta-buttons">
                <a href="register/" class="btn btn-white btn-lg">
                    <i class="bi bi-rocket-takeoff-fill"></i>
                    Join Now - It's Free
                </a>
                <a href="#contact" class="btn btn-outline btn-lg" style="border-color: white; color: white;">
                    <i class="bi bi-envelope-fill"></i>
                    Contact Us
                </a>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="section-header animate-on-scroll">
            <div class="section-badge">
                <i class="bi bi-geo-alt-fill"></i>
                Find Us
            </div>
            <h2>Get in Touch</h2>
            <p>Visit us or reach out - we'd love to hear from you!</p>
        </div>
        <div class="contact-grid">
            <div class="contact-info animate-on-scroll">
                <h3>Contact Information</h3>
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <div>
                        <h4>Address</h4>
                        <p><?php echo $street . ' ' . $hause_no . ', ' . $city . ', ' . $country; ?></p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="bi bi-telephone-fill"></i>
                    </div>
                    <div>
                        <h4>Phone</h4>
                        <p>+251 911 234 567</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="bi bi-envelope-fill"></i>
                    </div>
                    <div>
                        <h4>Email</h4>
                        <p>info@powerfitgym.com</p>
                    </div>
                </div>
            </div>
            <div class="hours animate-on-scroll">
                <h3>Opening Hours</h3>
                <div class="hours-grid">
                    <?php foreach ($days as $day): ?>
                        <div class="hour-item <?php echo is_null($day['open_time']) ? 'closed' : ''; ?>">
                            <span class="day"><?php echo $dayNames[$day['day']]; ?></span>
                            <span class="time">
                                <?php if (is_null($day['open_time'])): ?>
                                    Closed
                                <?php else: ?>
                                    <?php echo date('g:i A', strtotime($day['open_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($day['close_time'])); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-brand">
                <img src="assets/img/brand/logo.png" alt="<?php echo $business_name; ?>">
                <p><?php echo $about_us; ?></p>
                <div class="footer-social">
                    <a href="#"><i class="bi bi-facebook"></i></a>
                    <a href="#"><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-twitter-x"></i></a>
                    <a href="#"><i class="bi bi-youtube"></i></a>
                    <a href="#"><i class="bi bi-telegram"></i></a>
                </div>
            </div>
            <div class="footer-links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#trainers">Trainers</a></li>
                    <li><a href="#pricing">Pricing</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Support</h4>
                <ul>
                    <li><a href="login/">Member Login</a></li>
                    <li><a href="register/">Join Now</a></li>
                    <li><a href="prices/">All Plans</a></li>
                    <li><a href="trainers/">Our Trainers</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo $copyright_year; ?> <?php echo $business_name; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Mobile menu toggle
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('active');
        }

        // Scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            observer.observe(el);
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animate stats counter
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target + '+';
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current) + '+';
                }
            }, 30);
        }

        // Trigger counter animation when visible
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statElements = entry.target.querySelectorAll('.stat-item h3');
                    statElements.forEach(el => {
                        const value = parseInt(el.textContent);
                        if (!isNaN(value)) {
                            animateCounter(el, value);
                        }
                    });
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        const heroStats = document.querySelector('.hero-stats');
        if (heroStats) {
            statsObserver.observe(heroStats);
        }
    </script>
</body>
</html>

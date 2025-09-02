<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_name = $logged_in ? $_SESSION['user_name'] : '';

// Get indoor plants from database
$products = [];
$result = $conn->query("SELECT * FROM products WHERE category = 'indoor'");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indoor Plants</title>
    <link rel="stylesheet" href="../css/categoty.css">
</head>
<body>
    <header>
    <div class="top-header">
            <span>Welcome to BotaniQ!</span>
            <?php if ($logged_in): ?>
                <div class="user-account">
                    <span>Hi, <?php echo htmlspecialchars($user_name); ?></span>
                </div>
            <?php else: ?>
                <a href="./login.html" class="login-signup">
                    <img src="../icon/user.png"> Login / Sign Up
                </a>
            <?php endif; ?>
        </div>
        <div class="contact-bar">
            <div class="contact-item">
                <div class="contact-icon">
                    <img src="../icon/call.png">
                </div>
                <div class="contact-info">
                    <span class="contact-label">CALL US</span>
                    <span class="contact-text">+977 9812345670</span>
                </div>
            </div>
            
            <div class="contact-item">
                <div class="contact-icon">
                    <img src="../icon/location.png">
                </div>
                <div class="contact-info">
                    <span class="contact-label">LOCATION</span>
                    <span class="contact-text">Kathamndu,Nepal</span>
                </div>
            </div>
        </div>
        
        <div class="main-nav-container">
            <div class="logo-container">
                <div class="logo">
                    <img src="../image/BotaniQ.svg" alt="Logo" id="LOGO">
                </div>
            </div>
            
            <nav class="main-nav">
                <ul class="nav-links left-menu">
                    <li class="list"><a href="./e-commerce.php">HOME</a></li>
                    <li class="list"> <a href="#">SERVICES</a></li>
                    <li class="list"><a href="#">PRODUCTS</a></li>
                </ul>
                
                <ul class="nav-links right-menu">
                    <li class="list"><a href="#">BLOG</a></li>
                    <li class="list"><a href="#">ABOUT US</a></li>
                    <li class="list cart-icon">
                        <a href="#">
                            <img src="../icon/shopping-cart.png" alt="Cart">
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container">
        <section class="banner">
            <h1>Home > Indoor Plants</h1>
        </section>
        <div class="product-page-content">
            <div class="products-main-content">
                <div class="product-sorting">
                    <div class="product-count">
                        <p>Showing 1-12 of 24 products</p>
                    </div>
                    <div class="sort-options">
                        <select>
                            <option value="popularity">Sort by Popularity</option>
                            <option value="price-low-high">Price: Low to High</option>
                            <option value="price-high-low">Price: High to Low</option>
                            <option value="newest">Newest First</option>
                        </select>
                    </div>
                </div>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="../image/<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                            <div class="product-price">Rs. <?php echo htmlspecialchars($product['price']); ?></div>
                            <button class="add-to-cart-btn">Add to Cart</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
              
                
                <div class="pagination" style="display: flex; justify-content: center; margin-top: 40px;">
                    <a href="#" style="margin: 0 5px; padding: 8px 15px; border: 1px solid #ddd; text-decoration: none; color: #2e7d32;">1</a>
                    <a href="#" style="margin: 0 5px; padding: 8px 15px; border: 1px solid #ddd; text-decoration: none; color: #333;">2</a>
                    <a href="#" style="margin: 0 5px; padding: 8px 15px; border: 1px solid #ddd; text-decoration: none; color: #333;">Next →</a>
                </div>
            </div>
        </div>
        
        <section class="contact">
            <div class="contact_contain"  >
                <h2>Stay Updated with BotaniQ</h2>
                <p >Subscribe to our newsletter for plant care tips, new arrivals, and exclusive offers.</p>
                <form class="subject">
                    <input type="email" placeholder="Your Email Address" >
                    <button type="submit" >Subscribe</button>
                </form>
            </div>
        </section>
    </main>
    
    <footer class="footer">
        <div class="fcontain" >
            <div>
                <h3 >About BotaniQ</h3>
                <p>BotaniQ is Nepal's premier plant shop offering a wide selection of indoor and outdoor plants, gardening supplies, and expert advice for plant enthusiasts.</p>
            </div>
            
            <div class="flist">
                <h3 >Quick Links</h3>
                <ul >
                    <li><a href="#" >Home</a></li>
                    <li><a href="#" >Shop</a></li>
                    <li><a href="#" >Plant Care</a></li>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#" >Contact</a></li>
                </ul>
            </div>
            
            <div class="Fform">
                <h3>Contact Us</h3>
                <p><strong>Address:</strong> Kathmandu, Nepal</p>
                <p><strong>Phone:</strong> +977 9812345670</p>
                <p><strong>Email:</strong> info@botaniq.com.np</p>
            </div>
            
            <div class="social">
                <h3 >Follow Us</h3>
                <div class="social-image" >
                    <a href="#" >FB</a>
                    <a href="#" >IG</a>
                    <a href="#" >TW</a>
                    <a href="#" >YT</a>
                </div>
            </div>
        </div>
        
        <div style="max-width: 1200px; margin: 40px auto 0; padding: 20px; border-top: 1px solid #444; text-align: center; color: #ccc;">
            <p>&copy; 2025 BotaniQ. All Rights Reserved.</p>
        </div>
    </footer>
    <Script src="../javascript/category.js"></Script>
</body>
</html>
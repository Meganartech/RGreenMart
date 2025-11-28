<?php
require_once './env.php';

// Database connection
try {
    $conn = new PDO(
        "mysql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') . ";dbname=" . ($_ENV['DB_NAME'] ?? 'diwali_db') . ";charset=utf8mb4",
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? ''
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

// Fetch contact info from DB
$stmt = $conn->prepare("SELECT * FROM admin_details LIMIT 1");
$stmt->execute();
$contact = $stmt->fetch(PDO::FETCH_ASSOC);

$address = $contact['shopaddress'] ?? 'No address found';
$mobile = $contact['phone'] ?? '';
$mobile2 = $contact['phone2'] ?? '';
$office = $contact['office'] ?? '';
$email = $contact['email'] ?? '';
?>
<head>
            <meta name="keywords" content="Deepavali crackers sale 2025, Buy crackers online Deepavali 2025, Diwali crackers offer 2025, Deepavali discount crackers online, 
Diwali crackers shop near me, Deepavali crackers combo offer 2025, Wholesale Diwali crackers online, Sivakasi crackers online shopping, , 
Diwali crackers home delivery 2025, Best price Diwali crackers online, Cheapest Deepavali crackers online 2025, Eco-friendly Diwali crackers online 2025, Diwali crackers gift box sale 2025, Online cracker booking for Deepavali 2025, Buy Sivakasi crackers for Deepavali 2025, Buy crackers online Chennai Deepavali 2025, Diwali crackers sale Coimbatore 2025, Deepavali crackers shop Madurai 2025, 
Tirunelveli Deepavali crackers online, Salem Diwali crackers discount 2025, Deepavali crackers gift pack 2025, Green crackers for Diwali 2025, Cheap Diwali crackers online 2025, Buy Diwali crackers online Tamil Nadu 2025, Standard Fireworks Diwali crackers 2025, Ayyan Fireworks branded crackers online, Sony Fireworks crackers sale 2025, Sri Kaliswari branded crackers Deepavali 2025, RGreenMart crackers sale 2025, Trichy branded crackers discount Diwali 2025, Crackers online sale 2025, Buy crackers online Diwali 2025, Deepavali crackers sale 2025, Diwali crackers online shopping, Crackers combo offers 2025, Wholesale crackers online 2025, Discount crackers for Deepavali, Crackers price list online 2025, Online booking of Diwali crackers, Cheapest crackers sale online, Buy crackers combo packs online Deepavali 2025, Eco-friendly crackers online sale 2025, Sivakasi crackers home delivery 2025, Diwali crackers family pack offers, Order crackers online with free delivery, Crackers online sale Chennai 2025, Sivakasi crackers online shopping 2025, Deepavali crackers Coimbatore online, Diwali crackers Madurai offers 2025, Crackers shop near me Diwali 2025, Crackers combo pack offers Deepavali 2025, 
, Crackers online with discount, Festival crackers sale 2025, Diwali crackers mega offer online, Crackers shop online best price, Eco-friendly Crackers Online 2025, Sivakasi Crackers Home Delivery, Diwali cracker sale 2025, Diwali crackers online shopping 2025, Buy crackers online Diwali 2025, Crackers online sale for Diwali 2025, Online Diwali crackers offers 2025, Diwali firecrackers online sale 2025, Diwali crackers discount 2025, Cheap Diwali crackers online 2025, Diwali crackers booking online 2025, Diwali crackers shop online, Diwali crackers combo pack sale 2025, Buy Diwali crackers online with home delivery, Eco-friendly Diwali crackers online sale 2025, 
Diwali crackers price list 2025, Diwali crackers family pack offers, Diwali crackers mega sale 2025, Buy Diwali crackers at wholesale price, Diwali crackers with discount offers 2025, Diwali crackers free delivery 2025, Diwali crackers best combo deals, Eco-Friendly Diwali Crackers Sale 2025, Sivakasi Crackers Online Sale 2025, Sivakasi Crackers sale, Crackers online, Crackers online sale, Sony, sonny, Ayyan Fireworks, Standard Fireworks, Standard Fireworks, Sonny, Ayyan, Ramesh sparklers, Standard Fireworks, Ayyan Fireworks, Sony Fireworks, Anil Fireworks, Sri Kaliswari Fireworks, Ramesh Fireworks, Vijay Fireworks, Cock Brand (National Fireworks), Chota Chetan Fireworks, Ajanta Fireworks">
 <script src="../cart.js"></script>
</head>
<!-- ======= Contact Section ======= -->
   <?php include './header.php'; ?>
<section id="contact" class="contact py-5" style="background-color: #f8f9fa;">
    <div class="container" data-aos="fade-up">
        <div class="section-title text-center mb-5">
            <h2 style="font-size: 32px; font-weight: bold;" class="primaryclr">Contact Us</h2>
            <p style="font-size: 18px; color: #4b5563;">Get in touch with us for any inquiries or support regarding our eco-friendly crackers.</p>
        </div>

        <div class="row">
            <div class="col-lg-12 mb-4">
               <iframe style="border: 0; width: 100%; height: 270px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3930.574682685331!2d78.07374607479207!3d9.886004190213642!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3b00cfe9e0d71771%3A0xb00d568a6b1efdd6!2sTechnology%20Business%20Incubator%20(TCE-TBI)!5e0!3m2!1sen!2sin!4v1764237040608!5m2!1sen!2sin" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>

            <div class="col-lg-4 mt-4">
                <div class="info-container" style="background: #fff; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); padding: 20px;">
                    <div class="info-item mb-4">
                        <i class="fa-solid fa-location-dot secclr" style="font-size: 24px;"></i>
                        <div>
                            <h4 style="font-size: 18px; font-weight: bold; color: #1f2937;">Location:</h4>
                            <p style="color: #4b5563;"><?php echo nl2br(htmlspecialchars($address, ENT_QUOTES, 'UTF-8')); ?></p>
                        </div>
                    </div>
                    <div class="info-item mb-4">
                        <i class="fa-regular fa-envelope secclr" style="font-size: 24px;"></i>
                        <div>
                            <h4 style="font-size: 18px; font-weight: bold; color: #1f2937;">Email:</h4>
                            <p style="color: #4b5563;"><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fa-solid fa-phone secclr" style=" font-size: 24px;"></i>
                        <div>
                            <h4 style="font-size: 18px; font-weight: bold; color: #1f2937;">Call:</h4>
                            <p style="color: #4b5563;">
                                <?php echo htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8'); ?> ,
                                <?php echo htmlspecialchars($mobile2, ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($office): ?>
                                    <br><strong>Office:</strong> <?php echo htmlspecialchars($office, ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8  mt-lg-0">
                <div class="details-container" style="background: #fff; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); padding: 30px;">
                    <h2 style="text-center; font-size: 24px; font-weight: bold; color: #1f2937; margin-bottom: 20px;">Send Us a Message</h2>
                    <form action="forms/contact.php" method="post" role="form" class="php-email-form">
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <input type="text" name="name" class="form-control" id="name" placeholder="Your Name" required pattern="^[A-Za-z ]{2,}$" title="Enter a valid name (letters only)">
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <input type="email" class="form-control" name="email" id="email" placeholder="Your Email" required title="Enter a valid email address">
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <input type="text" class="form-control" name="subject" id="subject" placeholder="Subject" required>
                        </div>
                        <div class="form-group mb-3">
                            <textarea class="form-control" name="message" id="message" rows="5" placeholder="Message" required minlength="5" title="Enter your message"></textarea>
                        </div>
                        <div class="my-3">
                            <div class="loading">Loading</div>
                            <div class="error-message"></div>
                            <div class="sent-message">Your message has been sent. Thank you!</div>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(to right, #064e3b, #84cc16); border: none; padding: 10px 30px; border-radius: 50px; color:white">
                                Send Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section><!-- End Contact Section -->

<?php
require_once './footer.php';
$conn = null;
?>

<style>
.contact {
    padding: 60px 0;
    background-color: #f8f9fa;
}

.section-title h2 {
    font-size: 32px;
    font-weight: bold;
    color: #dc2626;
    margin-bottom: 20px;
}

.section-title p {
    font-size: 18px;
    color: #4b5563;
}

.info-container, .details-container {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 20px;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 20px;
}

.info-item i {
    font-size: 24px;
    color: #dc2626;
}

.info-item h4 {
    font-size: 18px;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 5px;
}

.info-item p {
    font-size: 16px;
    color: #4b5563;
    margin: 0;
}

.php-email-form .form-control {
    border: 1px solid #84cc16;
    border-radius: 8px;
    font-size: 16px;
    padding: 10px;
}
.form-control:focus {

     outline: 2px solid #059669;
    box-shadow: none;
}

.php-email-form .loading,
.php-email-form .error-message,
.php-email-form .sent-message {
    display: none;
    font-size: 14px;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
}

.php-email-form .loading {
    background: #fff7ed;
    color: #f97316;
}

.php-email-form .error-message {
    background: #fee2e2;
    color: #dc2626;
}

.php-email-form .sent-message {
    background: #e6ffed;
    color: #16a34a;
}

.php-email-form .loading.show,
.php-email-form .error-message.show,
.php-email-form .sent-message.show {
    display: block;
}

.btn-primary {
    background: linear-gradient(to right, #dc2626, #f97316);
    border: none;
    padding: 10px 30px;
    border-radius: 50px;
    font-weight: bold;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: linear-gradient(to right, #b91c1c, #ea580c);
    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.2);
}

@media (max-width: 991px) {
    .info-container {
        margin-bottom: 20px;
    }
}

@media (max-width: 576px) {
    .section-title h2 {
        font-size: 28px;
    }
    .section-title p {
        font-size: 16px;
    }
    .info-item i {
        font-size: 20px;
    }
    .info-item h4 {
        font-size: 16px;
    }
    .info-item p {
        font-size: 14px;
    }
    .details-container {
        padding: 15px;
    }
}
</style>

<script>
// Form submission handling
document.querySelector('.php-email-form').addEventListener('submit', function(event) {
    event.preventDefault();
    
    const form = this;
    const loading = form.querySelector('.loading');
    const errorMessage = form.querySelector('.error-message');
    const sentMessage = form.querySelector('.sent-message');
    
    // Show loading
    loading.classList.add('show');
    errorMessage.classList.remove('show');
    sentMessage.classList.remove('show');
    
    // Submit form via fetch
    fetch(form.action, {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        loading.classList.remove('show');
        if (data.success) {
            sentMessage.classList.add('show');
            form.reset();
        } else {
            errorMessage.textContent = data.message || 'An error occurred. Please try again.';
            errorMessage.classList.add('show');
        }
    })
    .catch(error => {
        loading.classList.remove('show');
        errorMessage.textContent = 'An error occurred. Please try again.';
        errorMessage.classList.add('show');
        console.error('Error:', error);
    });
});
</script>

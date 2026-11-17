# NextTrip — Smart Tourism Management System

NextTrip is a web application that connects travellers with travel agents and tour packages across India. Travellers can explore destinations, book trips, pay online, and share reviews. Travel agents can publish their own destinations, packages, and discount coupons. Administrators approve agents and content, manage bookings and users, and keep the website up to date.

The application is built with PHP and MySQL, with HTML, CSS, and JavaScript for the interface.

**Live site:** https://nexttrip.byethost7.com

The website is responsive and works on desktop, tablet, and phone screens.

---

## Table of Contents

- [Key Features](#key-features)
- [Technology Stack](#technology-stack)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
- [Usage](#usage)
- [Screenshots](#screenshots)
- [License](#license)
- [Developer](#developer)

---

## Key Features

**For travellers**
- Browse destinations with photos, maps, opening times, and entry fees.
- Search and filter tour packages by price, duration, and rating.
- Create an account with email verification (one-time code).
- Book a package for a chosen date, apply a coupon, and pay online.
- Download booking receipts as PDF.
- Save favourites, write reviews after a trip, and receive notifications.

**For travel agents**
- Apply for an agent account with identity and business documents.
- Submit destinations for review, then create packages from approved destinations.
- Create discount coupons for their own packages.
- Accept or reject booking requests.
- View booking and revenue summaries on a dashboard.

**For administrators**
- Verify agent documents and approve or reject applications.
- Review destination submissions.
- Manage packages, coupons, users, and bookings.
- Read customer enquiries and edit website settings.

---

## Technology Stack

- **Back end:** PHP, MySQL / MariaDB
- **Front end:** HTML5, CSS3, JavaScript
- **Payments:** Razorpay (test mode supported)
- **Email:** PHPMailer with SMTP
- **Maps:** Leaflet with OpenStreetMap
- **Charts:** Chart.js
- **PDF receipts:** jsPDF and html2canvas
- **Icons and typography:** Font Awesome, Bootstrap Icons, Google Fonts

---

## Project Structure

| Folder | Purpose |
|---|---|
| `index.php`, `destinations.php`, `packages.php` | Public pages |
| `auth/` | Sign-in, registration, and email verification |
| `user/` | Traveller area: bookings, reviews, notifications, settings |
| `agent/` | Travel agent dashboard |
| `admin/` | Administrator panel |
| `ajax/` | Endpoints for booking, payment, and forms |
| `includes/` | Shared helpers: database, mailer, navigation, booking logic |
| `config/` | Database, mail, and payment settings |
| `vendor/` | Third-party libraries (PHPMailer) |
| `uploads/` | Images and documents uploaded through the site |
| `smart_tourism.sql` | Complete database file |

---

## Getting Started

### Requirements

- PHP 8.0 or newer, with the `mysqli` and `curl` extensions
- MySQL 5.7 or newer, or MariaDB 10.3 or newer
- Apache, or any web server that runs PHP (for example XAMPP or WAMP)
- An SMTP email account, such as Gmail with an app password, for sending emails
- A Razorpay test account, for online payments (optional for browsing)

### Installation

1. **Copy the project folder** into your web server folder.
   - XAMPP (Windows): `C:\xampp\htdocs\nexttrip`
   - Linux: `/var/www/html/nexttrip`

2. **Create the database and import the file.**
   - Open phpMyAdmin and create a database named `smart_tourism`.
   - Select the database, open the **Import** tab, and choose `smart_tourism.sql`.
   - Click **Go**.

3. **Update the configuration files** in the `config/` folder (see below).

4. **Open the site** in your browser:

   ```text
   http://localhost/nexttrip/
   ```

### Configuration

| File | What to set |
|---|---|
| `config/config.php` | Database host, database name, username, and password |
| `config/mail_config.php` | SMTP host, port, sender address, and SMTP login |
| `config/razorpay.php` | Razorpay key ID and key secret (test keys while developing) |

Do not share your passwords or keys publicly.

---

## Usage

### Travellers

1. Open the home page and choose **Destinations** or **Packages** to browse.
2. Select a package to see its itinerary, price, reviews, and available dates.
3. Choose **Book now**, enter the travel date and number of travellers, and apply a coupon if you have one.
4. Complete the payment through Razorpay.
5. Open **My Bookings** to check the status, download the receipt, or write a review after the trip.

New travellers create an account from **Sign in**, then confirm their email with a six-digit code.

### Travel agents

1. Register as a travel agent and upload the required documents.
2. You receive an email confirming that your application is pending review.
3. An administrator verifies your documents and approves or rejects the application. You receive an email with the decision. If the application is rejected, the email includes the reason.
4. Once approved, sign in to access the agent dashboard. Until then, the dashboard is not available.
5. Submit a destination with its details, photos, and opening times.
6. After the destination is approved, create a package that includes it.
7. Create coupons for your packages and respond to booking requests.

### Administrators

1. Sign in and open the **Dashboard** for an overview.
2. Review each agent's identity and business documents, and approve or reject the application with a reason. Review destination submissions the same way.
3. Manage packages, coupons, users, and bookings from the sidebar.
4. Read contact-page messages under **Contact Enquiries**.
5. Update site-wide details under **Website Settings**.

---

## Screenshots

<div align="center">

**Homepage**

<img src="https://github.com/user-attachments/assets/9d5aece4-c02c-4bb0-98e1-177350208f43" width="85%">

<br><br>

**Tour Packages**

<img src="https://github.com/user-attachments/assets/c26d9139-68c9-4dcc-9092-efef4b855789" alt="Tour packages page" width="85%">

<br><br>

**User: My Bookings**

<img src="https://github.com/user-attachments/assets/402d4188-87bc-43fb-9740-c28e5de02a2a" alt="Admin contact enquiries" width="85%">

</div>

---

## License

This project does not currently include a license file. Until the copyright holder adds one, the code may not be copied, modified, or redistributed without written permission from the owner.

---

## Developer

**Jiya Odedara**
**Diploma in Computer Engineering**

---


## Acknowledgements

Developed as an academic project with the objective of improving the digital tourism experience through web-based technologies.



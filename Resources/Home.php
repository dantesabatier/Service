<?php /** @noinspection PhpUndefinedVariableInspection */ ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #1e1e1e;
            color: #e0e0e0;
        }

        a {
            text-decoration: none;
        }

        header {
            background: #121212;
            text-align: center;
            padding: 3rem 1rem;
        }

        header h1 {
            margin: 0;
            font-size: 2.5rem;
        }

        header p {
            margin: 0.5rem 0 0;
            font-size: 1rem;
            color: #bbbbbb;
        }

        main {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .hero {
            text-align: center;
            padding: 2.5rem 1rem;
            background: #2c2c2c;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
        }

        .hero h2 {
            margin: 0 0 1rem;
            color: #f0f0f0;
            font-size: 2rem;
        }

        .hero p {
            font-size: 1rem;
            line-height: 1.5;
            color: #ccc;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: #2c2c2c;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h3 {
            margin-top: 0;
            color: #f0f0f0;
            font-size: 1.2rem;
        }

        .card p {
            color: #ccc;
            line-height: 1.4;
            font-size: 0.95rem;
        }

        footer {
            text-align: center;
            font-size: 0.8rem;
            color: #888;
            margin: 3rem 0;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            header h1 {
                font-size: 2rem;
            }

            .hero h2 {
                font-size: 1.5rem;
            }

            .hero p {
                font-size: 0.95rem;
            }

            .card h3 {
                font-size: 1.1rem;
            }

            .card p {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            header {
                padding: 2rem 0.5rem;
            }

            main {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }

            .hero {
                padding: 2rem 0.5rem;
            }

            .cards {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
<header>
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Version <?= htmlspecialchars($shortVersion) ?> (<?= htmlspecialchars($version) ?>)</p>
</header>
<main>
    <div class="hero">
        <h2>Welcome to <?= htmlspecialchars($title) ?></h2>
        <p>Access all features securely and programmatically. Use our endpoints to integrate your applications with ease and confidence.</p>
    </div>
    <div class="cards">
        <div class="card">
            <h3>Authentication</h3>
            <p>Secure login and session management using JWT or session cookies. Ensure your applications remain protected at all times.</p>
        </div>
        <div class="card">
            <h3>Data Access</h3>
            <p>Retrieve, create, update, and delete resources programmatically via RESTful endpoints.</p>
        </div>
        <div class="card">
            <h3>Integration</h3>
            <p>Easy-to-use endpoints designed for rapid integration into your web or mobile applications.</p>
        </div>
    </div>
</main>
<footer>
    <?= htmlspecialchars($copyright) ?>
</footer>
</body>
</html>

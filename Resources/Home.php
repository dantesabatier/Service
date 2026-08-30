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
            font-family: "Inter", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #0f0f12;
            color: #d1d1d1;
            line-height: 1.6;
        }

        header {
            background: linear-gradient(180deg, #16161a 0%, #0f0f12 100%);
            text-align: center;
            padding: 3rem 1rem 5rem; /* Ajustado el padding inferior */
            border-bottom: 1px solid #2a2a30;
        }

        .logo-container {
            margin-bottom: 1.5rem;
        }

        .main-logo {
            width: 64px;
            height: 64px;
            filter: drop-shadow(0 0 12px rgba(79, 70, 229, 0.4));
        }

        header h1 {
            margin: 0;
            font-size: 3rem;
            background: linear-gradient(135deg, #fff 0%, #a5a5a5 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }

        header p {
            margin: 0.8rem 0 0;
            font-size: 1.1rem;
            color: #6e6e77;
            font-family: "SF Mono", "Cascadia Code", monospace;
        }

        main {
            max-width: 1100px;
            margin: -3rem auto 2rem;
            padding: 0 1.5rem;
        }

        .hero {
            text-align: center;
            padding: 3.5rem 2rem;
            background: rgba(30, 30, 35, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid #33333a;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            margin-bottom: 3rem;
        }

        .hero h2 {
            margin: 0 0 1.2rem;
            color: #ffffff;
            font-size: 2.2rem;
            font-weight: 600;
        }

        .hero p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
            color: #a1a1aa;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .card {
            background: #16161a;
            padding: 2rem;
            border-radius: 12px;
            border: 1px solid #2a2a30;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, #4f46e5, transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .card:hover {
            transform: translateY(-8px);
            border-color: #4f46e5;
            background: #1c1c21;
        }

        .card:hover::before {
            opacity: 1;
        }

        .card h3 {
            margin-top: 0;
            color: #e4e4e7;
            font-size: 1.4rem;
            margin-bottom: 1rem;
        }

        .card p {
            color: #888891;
            margin: 0;
            font-size: 1rem;
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
    <div class="logo-container">
        <svg class="main-logo" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 17L12 22L22 17" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 12L12 17L22 12" stroke="#4338ca" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <h1><?= htmlspecialchars($title) ?></h1>
    <p>Version <?= htmlspecialchars($shortVersion) ?> (<?= htmlspecialchars($version) ?>)</p>
</header>
<main>
    <div class="hero">
        <h2>Welcome to <?= htmlspecialchars($title) ?></h2>
        <p>An application generated with Singularity and running on the PHP 8.5+ Sabatier SDK.</p>
    </div>
    <div class="cards">
        <div class="card">
            <h3>Model-Driven REST</h3>
            <p>The generated Core Data model is immediately available through a secured REST API, without handwritten controllers or route registration.</p>
        </div>
        <div class="card">
            <h3>Extensible Application Runtime</h3>
            <p>Add responders, views, policies, jobs and MCP tools when the application needs behavior beyond its generated API.</p>
        </div>
        <div class="card">
            <h3>Sabatier SDK Stack</h3>
            <p>Foundation, CoreData and Service provide shared system primitives, managed persistence and application infrastructure.</p>
        </div>
    </div>
</main>
<footer>
    <?= htmlspecialchars($copyright) ?>
</footer>
</body>
</html>

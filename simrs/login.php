<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";

    $accounts = [
        "registration" => [
            "password" => "123456",
            "name" => "Jane Doe",
            "role" => "Registration"
        ],
        "doctor" => [
            "password" => "123456",
            "name" => "Dr. Andi Sentosa",
            "role" => "Emergency Doctor"
        ],
        "radiology" => [
            "password" => "123456",
            "name" => "Dr. Budi Wijaya",
            "role" => "Radiology Specialist"
        ]
    ];

    if (isset($accounts[$username]) && $accounts[$username]["password"] === $password) {
        $_SESSION["username"] = $username;
        $_SESSION["name"] = $accounts[$username]["name"];
        $_SESSION["role"] = $accounts[$username]["role"];

        if ($username === "radiology") {
            header("Location: radiology_dashboard.php");
        } elseif ($username === "registration") {
            header("Location: registration_dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit;
    }

    $error = "Username atau password salah.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMRS - Login</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #F4F7F8;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            width: 420px;
            background: #FFFFFF;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #183B4E;
            font-size: 30px;
            margin-bottom: 8px;
        }

        .logo p {
            color: #667781;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #183B4E;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #D7E1E5;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
        }

        .form-group input:focus {
            border-color: #17818F;
        }

        .login-button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 7px;
            background: #17818F;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .login-button:hover {
            background: #245B73;
        }

        .error {
            background: #FDECEC;
            color: #C94A4A;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-size: 13px;
            text-align: center;
        }

        .demo {
            margin-top: 25px;
            padding: 12px;
            background: #E8F4F5;
            border-radius: 7px;
            font-size: 12px;
            color: #245B73;
            line-height: 1.6;
        }

        .demo strong {
            color: #183B4E;
        }
    </style>
</head>

<body>
    <div class="login-container">

        <div class="logo">
            <h1>SIMRS</h1>
            <p>RS PERMATA MEDIKA</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>

            <button type="submit" class="login-button">Login</button>
        </form>

        <div class="demo">
            <strong>Demo Account</strong><br>
            Registration: registration / 123456<br>
            Emergency Doctor: doctor / 123456<br>
            Radiology: radiology / 123456
        </div>

    </div>
</body>
</html>

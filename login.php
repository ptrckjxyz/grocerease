<?php
include 'connection.php';
session_start();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $insertStatement = $conn->prepare('SELECT * FROM users WHERE ermail = ?');
    $insertstatement->bind_param('s', $email);
    $insertStatement->execute();

    $result = $insertStatement->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if(password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user ['user_id'];
            $_SESSION['firstName'] = $user ['firstName'];
            $_SESSION['lastName'] = $user ['lastName'];
            $_SESSION['email'] = $user ['email'];

            header('Location: index.php');
            exit();
            
        }

        else {
            echo "INVALID PASSWORD!";
        }
    }

    else {
        echo "EMAIL NOT FOUND!";
    }

    $insertStatement->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOGIN FORM</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <div id="container">
        <!-- RIGHT SIDE -->
        <div id="rightContainer">
            <div class="welcome-section">
                <h4>Welcome back!</h4>
                <div class="logo- section">
                    <h5>Grocer<span class="green">Ease</span></h5>
                    <img id="logo2" src="/icons/2ff50d7e-e5cf-4ff4-ad0e-15d159f23be1-removebg-preview.png" alt="GrocerEase Logo">
                </div>
            </div>

            <p id="paragraph2">Sign in to access your dashboard</p>

            <form class="login-form" action=login.php method='POST'>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" class="textbox" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" class="textbox" name="password" required>
                </div>

                <div class="form-options">
                    <div class="remember-section">
                        <input type="checkbox" name="remember" id="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-link">
                        <p id="forgot">Forgot password?</p>
                    </a>
                </div>

                <input id="signin" type="submit" value="Sign in">

                <div class="signup-link">
                    <a href="registration.html">
                        <p id="link">New to GrocerEase? <span class="green">Create an account</span></p>
                    </a>
                </div>
            </form>
        </div>

        <!-- LEFT SIDE -->
        <div id="leftContainer">
            <div class="brand-section">
                <div class="inline">
                    <h1>Grocer<span class="green">Ease</span></h1>
                    <img id="logo" src="/icons/2ff50d7e-e5cf-4ff4-ad0e-15d159f23be1-removebg-preview.png" alt="GrocerEase Logo">
                </div>
            </div>

            <div class="content-section">
                <h2>Smart grocery management.</h2>
                <h3>Plan meals, track inventory, and optimize your budget - all in one <br> place.</h3>

                <div id="featuresContainer">
                    <div class="feature-item">
                        <img class="icons" src="/icons/icons8-delivery-time-50.png" alt="time">
                        <p class="paragraph">Real-time inventory & expiration tracking.</p>
                    </div>

                    <div class="feature-item">
                        <img class="icons" src="/icons/icons8-suggestion-64.png" alt="suggestions">
                        <p class="paragraph">Smart meal suggestions from your pantry.</p>
                    </div>

                    <div class="feature-item">
                        <img class="icons" src="/icons/icons8-analytics-50.png" alt="analytics">
                        <p class="paragraph">Cost analytics to reduce food waste.</p>
                    </div>
                    
                </div>
            </div>


        </div>
    </div>
    
</body>
</html>
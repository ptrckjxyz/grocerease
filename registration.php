<?php
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $insertStatement = $conn->prepare("INSERT INTO users (firstName, lastName, email, password) VALUES (?, ?, ?, ?)");
    $insertStatement->bind_param("ssss", $firstName, $lastName, $email, $password);

    if ($insertStatement->execute()) {
      header('Location: login.php');
      exit;
    }

    else {
        echo "Erorr: " . $insertStatement->error;
    }

    $insertStatement->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
    <link rel="stylesheet" href="registration.css">
</head>
<body>

    <div id="container">
        <!-- RIGHT SIDE -->
        <div id="rightContainer">

            <div class="welcome-section">
                <h4>Create an account</h4>
            </div>

            <p id="paragraph2">It only takes a minute.</p>

            <form class="login-form" action="registration.php" method="POST">
                <div class="form-group">
                    <label for="firstName">First Name:</label>
                    <input type="text" class="textbox" name="firstName" required>
                </div>

                <div class="form-group">
                    <label for="lastName">Last Name:</label>
                    <input type="text" class="textbox" name="lastName" required>
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" class="textbox" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" class="textbox" name="password" required>
                </div>

                <div class="form-group">
                    <label for="confirm">Confirm Password:</label>
                    <input type="password" class="textbox" name="confirm" required>
                </div>

                <input id="signin" type="submit" value="Create Account">

                <div class="signup-link">
                    <a href="login.html">
                        <p id="link">Already have an account? <span class="green">Sign in</span></p>
                    </a>
                </div>
            </form>
        </div>

        <!-- LEFT SIDE -->
        <div id="leftContainer">
            <div class="brand-section">
                <div class="inline">
                  <h1>
                      Grocer
                      <span class="green">
                          Ease
                          <img src="image/logo.png" alt="GrocerEase Logo" class="logo-img">
                      </span>
                  </h1>
              </div>
            </div>

            <div class="content-section">
                <h2>Join Grocer<span class="green">Ease</span> today.</h2>
                <h3>Create an account and start managing groceries the smarter<br> way.</h3>

                <div id="featuresContainer">
                    <div class="feature-item">
                        <img class="icons" src="/icons/icons8-list-50.png" alt="list">
                        <p class="paragraph">Real-time inventory & expiration tracking.</p>
                    </div>

                    <div class="feature-item">
                        <img class="icons" src="/icons/icons8-meal-50.png" alt="meal">
                        <p class="paragraph">Smart meal suggestions from your pantry.</p>
                    </div>

                    <div class="feature-item">
                        <img class="icons" src="/icons/icons8-customer-insight-64.png" alt="insight">
                        <p class="paragraph">Cost analytics to reduce food waste.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</body>
</html>
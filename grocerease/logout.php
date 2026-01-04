<?php
session_start();

// If the user confirmed logout (clicked "Yes")
if (isset($_POST['confirm_logout'])) {
    $_SESSION = [];
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logout | GrocerEase</title>
<link rel="shortcut icon" href="image/logo.png" type="image/x-icon">
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #f8f9fa;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
  }

  /* Modal Background */
  .modal {
    display: flex;
    justify-content: center;
    align-items: center;
    position: fixed;
    z-index: 1000;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.2s ease;
  }

  /* Modal Box - Compact & Professional */
  .modal-content {
    background: #ffffff;
    border-radius: 12px;
    padding: 24px;
    width: 320px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    animation: slideUp 0.2s ease;
  }

  /* Icon - Minimalist */
  .logout-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 16px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .logout-icon svg {
    width: 24px;
    height: 24px;
    fill: #6c757d;
  }

  /* Header - Compact */
  .modal-content h2 {
    margin-bottom: 8px;
    color: #212529;
    font-size: 18px;
    font-weight: 600;
    letter-spacing: -0.3px;
  }

  /* Message - Subtle */
  .modal-content p {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 24px;
    line-height: 1.5;
  }

  /* Buttons container */
  .buttons {
    display: flex;
    gap: 12px;
  }

  /* Buttons - Professional & Compact */
  .btn {
    flex: 1;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
  }

  /* Logout button */
  .btn-yes {
    background: #dc3545;
    color: #fff;
  }

  .btn-yes:hover {
    background: #c82333;
    transform: translateY(-1px);
  }

  .btn-yes:active {
    transform: translateY(0);
  }

  /* Cancel button */
  .btn-no {
    background: #ffffff;
    color: #495057;
    border: 1px solid #dee2e6;
  }

  .btn-no:hover {
    background: #f8f9fa;
    border-color: #adb5bd;
  }

  .btn-no:active {
    transform: scale(0.98);
  }

  /* Animations */
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  @keyframes slideUp {
    from { 
      opacity: 0;
      transform: translateY(20px);
    }
    to { 
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* Responsive */
  @media (max-width: 480px) {
    .modal-content {
      width: 90%;
      max-width: 320px;
    }
  }

</style>
</head>
<body>

  <!-- Logout Confirmation Modal -->
  <div id="logoutModal" class="modal">
    <div class="modal-content">
      <div class="logout-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
        </svg>
      </div>
      <h2>Log Out?</h2>
      <p>Are you sure you want to log out?</p>
      <form method="POST">
        <div class="buttons">
          <button type="button" class="btn btn-no" id="cancelLogout">Cancel</button>
          <button type="submit" name="confirm_logout" class="btn btn-yes">Log Out</button>
        </div>
      </form>
    </div>
  </div>

<script>
  // When user clicks "Cancel", return to dashboard
  document.getElementById('cancelLogout').addEventListener('click', function() {
    window.location.href = 'dashboard.php';
  });
</script>

</body>
</html>
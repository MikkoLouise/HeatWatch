<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HeatWatch - Login</title>
<style>
  body {
    font-family: monospace;
    background: #111;
    color: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
  }
  .box {
    background: #222;
    padding: 2rem;
    border-radius: 8px;
    width: 300px;
  }
  h2 {
    margin-bottom: 1rem;
    color: #ff4c1c;
  }
  input {
    width: 100%;
    padding: 0.5rem;
    margin-bottom: 1rem;
    background: #333;
    border: 1px solid #444;
    color: #fff;
    border-radius: 4px;
    box-sizing: border-box;
  }
  button {
    width: 100%;
    padding: 0.6rem;
    background: #ff4c1c;
    border: none;
    color: #fff;
    border-radius: 4px;
    cursor: pointer;
  }
</style>
</head>
<body>
<div class="box">
  <h2>HeatWatch</h2>
  <p style="margin-bottom:1rem; color:#aaa; font-size:0.85rem;">Barangay Health Monitoring System</p>
  <form method="POST">
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Login</button>
  </form>
</div>
</body>
</html>

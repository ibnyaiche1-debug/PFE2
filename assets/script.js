// =======================================================
// InternGo - Global JavaScript
// =======================================================

document.addEventListener("DOMContentLoaded", () => {

  /* ===================================================
     Show / Hide password (login & register pages)
     =================================================== */
  const togglePwdBtn = document.getElementById("togglePwd");
  const passwordInput = document.getElementById("pwd");
  const eyeIcon = document.getElementById("eyeIcon");

  if (togglePwdBtn && passwordInput && eyeIcon) {
    togglePwdBtn.addEventListener("click", () => {
      const isPassword = passwordInput.type === "password";

      passwordInput.type = isPassword ? "text" : "password";
      eyeIcon.className = isPassword
        ? "bi bi-eye-slash"
        : "bi bi-eye";
    });
  }

  /* ===================================================
     Auto-hide alerts (optional UX improvement)
     =================================================== */
  const alerts = document.querySelectorAll(".alert");

  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = "opacity 0.5s ease";
      alert.style.opacity = "0";

      setTimeout(() => {
        alert.remove();
      }, 500);
    }, 4000); // 4 seconds
  });

  /* ===================================================
     Small hover animation for buttons (extra polish)
     =================================================== */
  const coolButtons = document.querySelectorAll(".btn-cool");

  coolButtons.forEach(btn => {
    btn.addEventListener("mouseenter", () => {
      btn.style.transform = "translateY(-2px)";
    });

    btn.addEventListener("mouseleave", () => {
      btn.style.transform = "translateY(0)";
    });
  });

});

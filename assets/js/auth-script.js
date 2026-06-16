// Loader functions
window.onload = function () {
  document.getElementById("loader").style.display = "none";
};

window.onbeforeunload = function () {
  document.getElementById("loader").style.display = "flex";
};

function showLoader() {
  document.getElementById("loader").style.display = "flex";
}

// Form type selection
function showForm(type) {
  document.getElementById("authSelection").classList.add("d-one");
  sessionStorage.setItem("authFormType", type);

  if (type === "student") {
    document.getElementById("studentFormContainer").classList.remove("d-one");
  } else {
    document.getElementById("staffFormContainer").classList.remove("d-one");
  }
}

function goBack() {
  sessionStorage.removeItem("authFormType");
  document.getElementById("authSelection").classList.remove("d-one");
  document.getElementById("studentFormContainer").classList.add("d-one");
  document.getElementById("staffFormContainer").classList.add("d-one");
}

document.addEventListener("DOMContentLoaded", () => {
  const saved = sessionStorage.getItem("authFormType");
  if (saved) showForm(saved);
});

// Per-step client-side validation
function validateStep(stepEl) {
  const fieldLabels = {
    student_name: "Full Name",
    reg_no: "Registration Number",
    student_email: "Email Address",
    staff_name: "Full Name",
    staff_id: "Staff ID",
    staff_email: "Email Address",
    phone_number: "Phone Number",
    password: "Password",
    confirm_password: "Confirm Password",
  };

  for (const input of stepEl.querySelectorAll("input[required]")) {
    const label = fieldLabels[input.name] || "This field";
    const val = input.value.trim();

    if (!val) return `${label} is required.`;

    if (input.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
      return "Please enter a valid email address.";
    }

    if (input.pattern && !new RegExp(input.pattern).test(input.value)) {
      return input.title || `${label} format is invalid.`;
    }
  }

  const pw = stepEl.querySelector("input[name='password']");
  const cpw = stepEl.querySelector("input[name='confirm_password']");
  if (pw && pw.value) {
    const p = pw.value;
    if (p.length < 8) return "Password must be at least 8 characters long.";
    if (!/[A-Z]/.test(p))
      return "Password must contain at least one uppercase letter.";
    if (!/[a-z]/.test(p))
      return "Password must contain at least one lowercase letter.";
    if (!/[0-9]/.test(p)) return "Password must contain at least one number.";
    if (!/[^A-Za-z0-9]/.test(p))
      return "Password must contain at least one special character.";
    if (cpw && p !== cpw.value) return "Passwords do not match.";
  }

  return null;
}

function showStepError(stepEl, message) {
  const errorDiv = stepEl.querySelector(".step-error");
  if (!errorDiv) return;
  errorDiv.textContent = message;
  errorDiv.classList.remove("d-none");
  errorDiv.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

function clearStepError(stepEl) {
  const errorDiv = stepEl.querySelector(".step-error");
  if (errorDiv) errorDiv.classList.add("d-none");
}

// Multi-step forms (supports multiple forms on same page)
function initMultiStepForm(formEl) {
  const steps = formEl.querySelectorAll(".form-step");
  if (!steps.length) return;

  const circles = formEl.querySelectorAll(".step-circle");
  const stepNumText = formEl.querySelector("#stepNumber");

  const getCurrentStepIndex = () =>
    Math.max(
      0,
      Array.from(steps).findIndex((s) => s.classList.contains("active")),
    );

  const setStep = (nextIndex) => {
    const currentIndex = getCurrentStepIndex();
    if (nextIndex < 0 || nextIndex >= steps.length) return;
    steps[currentIndex].classList.remove("active");
    steps[nextIndex].classList.add("active");

    if (circles.length) {
      circles.forEach((circle, index) => {
        circle.classList.toggle("active", index <= nextIndex);
      });
    }
    if (stepNumText) stepNumText.innerText = String(nextIndex + 1);
  };

  formEl.querySelectorAll(".next-step").forEach((button) => {
    button.addEventListener("click", () => {
      const currentStep = steps[getCurrentStepIndex()];
      const error = validateStep(currentStep);
      if (error) {
        showStepError(currentStep, error);
        return;
      }
      clearStepError(currentStep);
      setStep(getCurrentStepIndex() + 1);
    });
  });

  formEl.querySelectorAll(".prev-step").forEach((button) => {
    button.addEventListener("click", () => {
      clearStepError(steps[getCurrentStepIndex()]);
      setStep(getCurrentStepIndex() - 1);
    });
  });
}

document.addEventListener("DOMContentLoaded", () => {
  document
    .querySelectorAll("form")
    .forEach((formEl) => initMultiStepForm(formEl));
});

// Toast alert
document.addEventListener("DOMContentLoaded", function () {
  var toastElList = [].slice.call(document.querySelectorAll(".toast"));
  var toastList = toastElList.map(function (toastEl) {
    return new bootstrap.Toast(toastEl, { autohide: true, delay: 5000 }).show();
  });
});

// Password visibility toggle
function togglePwd(inputId, btn) {
  var input = document.getElementById(inputId);
  var icon  = btn.querySelector("i");
  if (input.type === "password") {
    input.type = "text";
    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
  } else {
    input.type = "password";
    icon.classList.remove("fa-eye");
    icon.classList.add("fa-eye-slash");
  }
}

// Real-time field validation
document.addEventListener("DOMContentLoaded", function () {
  function applyValidity(input, errorMsg) {
    var fb = input.nextElementSibling;
    if (!fb || !fb.classList.contains("invalid-feedback")) {
      fb = input.parentElement.querySelector(".invalid-feedback");
    }
    if (errorMsg) {
      input.classList.add("is-invalid");
      input.classList.remove("is-valid");
      if (fb) fb.textContent = errorMsg;
    } else {
      input.classList.remove("is-invalid");
      input.classList.add("is-valid");
      if (fb) fb.textContent = "";
    }
  }

  function watch(input, validatorFn) {
    if (!input) return;
    ["input", "blur"].forEach(function (evt) {
      input.addEventListener(evt, function () {
        applyValidity(input, validatorFn(input.value.trim()));
      });
    });
  }

  function vName(val) {
    if (!val) return "Full name is required.";
    if (val.length < 2) return "Name must be at least 2 characters.";
    if (!/^[A-Za-z\s'-]+$/.test(val)) return "Name can only contain letters.";
    return "";
  }

  function vEmail(val) {
    if (!val) return "Email is required.";
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)
      ? ""
      : "Enter a valid email address.";
  }

  function vRegNo(val) {
    if (!val) return "Registration number is required.";
    return /^202[0-9]-04-[0-9]{5}$/.test(val)
      ? ""
      : "Use format 202X-04-XXXXX (e.g. 2024-04-00123).";
  }

  function vStaffId(val) {
    if (!val) return "Staff ID is required.";
    return /^UDSM-STAFF-[0-9]{5}$/.test(val)
      ? ""
      : "Use format UDSM-STAFF-XXXXX (e.g. UDSM-STAFF-00123).";
  }

  function vPhone(val) {
    if (!val) return "Phone number is required.";
    return /^0[0-9]{9}$/.test(val)
      ? ""
      : "Must be 10 digits starting with 0 (e.g. 0712345678).";
  }

  function updatePwdRules(pwdInput, val) {
    var container = pwdInput.closest("form");
    if (!container) return;
    var rules = container.querySelectorAll(".pwd-rules li[data-rule]");
    var checks = {
      length: val.length >= 8,
      upper: /[A-Z]/.test(val),
      lower: /[a-z]/.test(val),
      number: /[0-9]/.test(val),
      special: /[^A-Za-z0-9]/.test(val),
    };
    rules.forEach(function (li) {
      var ok = !!checks[li.dataset.rule];
      li.classList.toggle("rule-ok", ok);
      li.querySelector("i").className = ok
        ? "fas fa-check-circle me-1"
        : "fas fa-circle me-1";
    });
  }

  function vPassword(val, pwdInput) {
    if (pwdInput) updatePwdRules(pwdInput, val);
    if (!val) return "Password is required.";
    if (val.length < 8) return "At least 8 characters required.";
    if (!/[A-Z]/.test(val)) return "Add at least one uppercase letter.";
    if (!/[a-z]/.test(val)) return "Add at least one lowercase letter.";
    if (!/[0-9]/.test(val)) return "Add at least one number.";
    if (!/[^A-Za-z0-9]/.test(val)) return "Add at least one special character.";
    return "";
  }

  function watchPasswordPair(pwdInput, cpwdInput) {
    if (!pwdInput || !cpwdInput) return;
    ["input", "blur"].forEach(function (evt) {
      pwdInput.addEventListener(evt, function () {
        applyValidity(pwdInput, vPassword(pwdInput.value, pwdInput));
        if (cpwdInput.value) {
          applyValidity(
            cpwdInput,
            pwdInput.value === cpwdInput.value ? "" : "Passwords do not match.",
          );
        }
      });
      cpwdInput.addEventListener(evt, function () {
        applyValidity(
          cpwdInput,
          pwdInput.value === cpwdInput.value ? "" : "Passwords do not match.",
        );
      });
    });
  }

  // Student form
  watch(document.getElementById("s_name"), vName);
  watch(document.getElementById("s_reg_no"), vRegNo);
  watch(document.getElementById("s_email"), vEmail);
  watch(document.getElementById("s_phone"), vPhone);
  watchPasswordPair(
    document.getElementById("s_pwd"),
    document.getElementById("s_cpwd"),
  );

  // Staff form
  watch(document.getElementById("st_name"), vName);
  watch(document.getElementById("st_staff_id"), vStaffId);
  watch(document.getElementById("st_phone"), vPhone);
  watch(document.getElementById("st_email"), vEmail);
  watchPasswordPair(
    document.getElementById("st_pwd"),
    document.getElementById("st_cpwd"),
  );

  // Reset password form
  watchPasswordPair(
    document.getElementById("resetPwd"),
    document.getElementById("resetCpwd"),
  );
});

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
    student_name:     "Full Name",
    reg_no:           "Registration Number",
    student_email:    "Email Address",
    staff_name:       "Full Name",
    staff_id:         "Staff ID",
    staff_email:      "Email Address",
    phone_number:     "Phone Number",
    password:         "Password",
    confirm_password: "Confirm Password",
  };

  for (const input of stepEl.querySelectorAll("input[required]")) {
    const label = fieldLabels[input.name] || "This field";
    const val   = input.value.trim();

    if (!val) return `${label} is required.`;

    if (input.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
      return "Please enter a valid email address.";
    }

    if (input.pattern && !new RegExp(input.pattern).test(input.value)) {
      return input.title || `${label} format is invalid.`;
    }
  }

  const pw  = stepEl.querySelector("input[name='password']");
  const cpw = stepEl.querySelector("input[name='confirm_password']");
  if (pw && pw.value) {
    const p = pw.value;
    if (p.length < 8)            return "Password must be at least 8 characters long.";
    if (!/[A-Z]/.test(p))        return "Password must contain at least one uppercase letter.";
    if (!/[a-z]/.test(p))        return "Password must contain at least one lowercase letter.";
    if (!/[0-9]/.test(p))        return "Password must contain at least one number.";
    if (!/[^A-Za-z0-9]/.test(p)) return "Password must contain at least one special character.";
    if (cpw && p !== cpw.value)  return "Passwords do not match.";
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

  const circles     = formEl.querySelectorAll(".step-circle");
  const stepNumText = formEl.querySelector("#stepNumber");

  const getCurrentStepIndex = () =>
    Math.max(0, Array.from(steps).findIndex((s) => s.classList.contains("active")));

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
  document.querySelectorAll("form").forEach((formEl) => initMultiStepForm(formEl));
});

// Toast alert
document.addEventListener("DOMContentLoaded", function () {
  var toastElList = [].slice.call(document.querySelectorAll(".toast"));
  var toastList = toastElList.map(function (toastEl) {
    return new bootstrap.Toast(toastEl, { autohide: true, delay: 5000 }).show();
  });
});

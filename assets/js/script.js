// Sidebar toggle
// document
//   .getElementById("sidebarCollapse")
//   .addEventListener("click", function () {
//     document.getElementById("sidebar").classList.toggle("active");
//   });

// document.addEventListener("DOMContentLoaded", function () {
//   const sidebar = document.getElementById("sidebar");
//   const sidebarCollapse = document.getElementById("sidebarCollapse");

//   // 1. Check local storage on page load
//   const sidebarStatus = localStorage.getItem("sidebarStatus");
//   if (sidebarStatus === "collapsed") {
//     sidebar.classList.add("active");
//   }

//   // 2. Toggle and Save state
//   sidebarCollapse.addEventListener("click", function () {
//     sidebar.classList.toggle("active");

//     // Save the current state
//     if (sidebar.classList.contains("active")) {
//       localStorage.setItem("sidebarStatus", "collapsed");
//     } else {
//       localStorage.setItem("sidebarStatus", "expanded");
//     }
//   });
// });

// document.addEventListener("DOMContentLoaded", function () {
//   const sidebar = document.getElementById("sidebar");
//   const sidebarCollapse = document.getElementById("sidebarCollapse");

//   // Load saved state
//   if (localStorage.getItem("sidebarStatus") === "permanent-expanded") {
//     sidebar.classList.add("expanded");
//   }

//   // Toggle Permanent State
//   sidebarCollapse.addEventListener("click", function () {
//     sidebar.classList.toggle("expanded");

//     if (sidebar.classList.contains("expanded")) {
//       localStorage.setItem("sidebarStatus", "permanent-expanded");
//     } else {
//       localStorage.setItem("sidebarStatus", "collapsed");

//       const openSubmenus = sidebar.querySelectorAll(".collapse.show");
//       openSubmenus.forEach((menu) => menu.classList.remove("show"));
//     }
//   });
// });

document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("sidebar");
  const sidebarCollapse = document.getElementById("sidebarCollapse");

  // Create an overlay fro mobile if not exists
  if (!document.querySelector(".overlay")) {
    const overlay = document.createElement("div");
    overlay.classList.add("overlay");
    document.body.appendChild(overlay);
  }
  const overlay = document.querySelector(".overlay");

  // Desktop persistence
  if (
    window.innerWidth >= 768 &&
    localStorage.getItem("sidebarStatus") === "permanent-expanded"
  ) {
    sidebar.classList.add("expanded");
  }

  sidebarCollapse.addEventListener("click", function () {
    if (window.innerWidth < 768) {
      // Mobile
      sidebar.classList.toggle("active");
      overlay.classList.toggle("active");
    } else {
      // Desktop
      sidebar.classList.toggle("expanded");

      if (sidebar.classList.contains("expanded")) {
        localStorage.setItem("sidebarStatus", "permanent-expanded");
      } else {
        localStorage.setItem("sidebarStatus", "collapsed");
      }
    }
  });

  overlay.addEventListener("click", function () {
    sidebar.classList.remove("active");
    overlay.classList.remove("active");
  });
});

// Active menu highlighting with query params
const currentURL = window.location.pathname.split("/").pop();
const links = document.querySelectorAll("#sidebar ul li a");

links.forEach((link) => {
  const linkURL = link.getAttribute("href");

  // Remove query params from link too
  const cleanLinkURL = linkURL.split("?")[0];

  if (currentURL === cleanLinkURL) {
    link.parentElement.classList.add("active");
  } else {
    link.parentElement.classList.remove("active");
  }
});

// Submenu
$(document).ready(function () {
  // Getcurrent page URL
  var url = window.location.href;

  // Find the link that matches the current URL
  $("#sidebar ul li a")
    .filter(function () {
      return this.href === url;
    })
    .parent("li")
    .addClass("active-link");

  // Find the parent collapse menu and open it
  $(".collapse")
    .has("li.active-link")
    .addClass("show")
    .prev("a")
    .attr("aria-expanded", "true")
    .closest("li")
    .addClass("active");
});

$(document).ready(function () {
  $("#userSubmenu").on("show.bs.collapse", function () {
    $(this).prev("a").find(".bi-chevron-down").addClass("rotate-180");
  });

  $("#userSubmenu").on("hide.bs.collapse", function () {
    $(this).prev("a").find(".bi-chevron-down").removeClass("rotate-180");
  });
});

// Loader
window.onload = function () {
  document.getElementById("loader").style.display = "none";
};

window.onbeforeunload = function () {
  document.getElementById("loader").style.display = "flex";
};

// Submit button loader
function showLoader() {
  document.getElementById("loader").style.display = "flex";
}

// Management tabs js
$(document).ready(function () {
  if (!$("#userManagementTabs").length) return;
  // switchTab is defined inline in user_management.php — re-trigger here so
  // DataTable column widths are adjusted after tables have been initialised.
  if (typeof window.switchTab === "function") {
    const hash = location.hash.slice(1);
    const VALID_TABS = ["students", "staff", "approval", "roles"];
    window.switchTab(VALID_TABS.includes(hash) ? hash : "students");
  }
});

// Function to capitallize words
function capitalizeWords(input) {
  if (typeof input.value !== "string" || input.value.length === 0) return;
  input.value = input.value.replace(/\b\w/g, function (char) {
    return char.toUpperCase();
  });
}

// Toast alert
document.addEventListener("DOMContentLoaded", function () {
  var toastElList = [].slice.call(document.querySelectorAll(".toast"));
  var toastList = toastElList.map(function (toastEl) {
    return new bootstrap.Toast(toastEl, { autohide: true, delay: 5000 }).show();
  });
});

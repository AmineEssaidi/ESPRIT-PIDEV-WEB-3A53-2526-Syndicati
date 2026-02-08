(() => {
  const STORAGE_KEY = "horizon_admin_sidebar_expanded";

  function setExpanded(expanded) {
    const root = document.documentElement;
    root.classList.toggle("admin-sidebar-expanded", expanded);
    try {
      localStorage.setItem(STORAGE_KEY, expanded ? "1" : "0");
    } catch (e) {
      // ignore
    }
  }

  function getInitialExpanded() {
    try {
      const v = localStorage.getItem(STORAGE_KEY);
      if (v === "1") return true;
      if (v === "0") return false;
    } catch (e) {
      // ignore
    }
    return false;
  }

  document.addEventListener("DOMContentLoaded", () => {
    const toggleBtn = document.querySelector(".admin-sidebar-toggle");
    if (!toggleBtn) return;

    setExpanded(getInitialExpanded());

    toggleBtn.addEventListener("click", () => {
      const expanded = document.documentElement.classList.contains("admin-sidebar-expanded");
      setExpanded(!expanded);
    });
  });
})();


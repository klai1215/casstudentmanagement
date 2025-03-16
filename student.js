function toggleSidebar() {
    let sidebar = document.getElementById("sidebar");
    let mainContent = document.querySelector(".main-content");

    if (sidebar.classList.contains("hidden")) {
        sidebar.classList.remove("hidden");
        mainContent.classList.remove("expanded");
    } else {
        sidebar.classList.add("hidden");
        mainContent.classList.add("expanded");
    }
}

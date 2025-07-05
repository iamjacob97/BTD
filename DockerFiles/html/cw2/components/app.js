// DOM Elements
const toggleButton = document.getElementById('toggle-btn');
const sidebar = document.getElementById('sidebar');

// Constants for class names
const CLASSES = {
  CLOSE: 'close',
  ROTATE: 'rotate',
  SHOW: 'show'
};

/**
 * Toggles the sidebar open/closed state
 * @param {Event} [event] - Optional event object
 */
function toggleSidebar(event) {
  if (event) event.preventDefault();
  
  sidebar.classList.toggle(CLASSES.CLOSE);
  toggleButton.classList.toggle(CLASSES.ROTATE);
  closeAllSubMenus();
}

/**
 * Toggles a submenu open/closed state
 * @param {HTMLElement} button - The button that triggers the submenu
 */
function toggleSubMenu(button) {
  const subMenu = button.nextElementSibling;
  
  if (!subMenu.classList.contains(CLASSES.SHOW)) {
    closeAllSubMenus();
  }
  
  subMenu.classList.toggle(CLASSES.SHOW); // Toggle the submenu open/closed
  button.classList.toggle(CLASSES.ROTATE); // Rotate the button arrow
  
  if (sidebar.classList.contains(CLASSES.CLOSE)) {
    sidebar.classList.remove(CLASSES.CLOSE); // Open the sidebar
    toggleButton.classList.remove(CLASSES.ROTATE); // Reset button rotation
  }
}

function closeAllSubMenus() {
  const openSubMenus = sidebar.getElementsByClassName(CLASSES.SHOW);
  Array.from(openSubMenus).forEach(submenu => {
    submenu.classList.remove(CLASSES.SHOW);
    submenu.previousElementSibling.classList.remove(CLASSES.ROTATE);
  });
}

// Event Listeners
document.addEventListener('DOMContentLoaded', () => {
  // Close submenus when clicking outside
  document.addEventListener('click', (event) => {
    if (!sidebar.contains(event.target)) {
      closeAllSubMenus();
    }
  });
});




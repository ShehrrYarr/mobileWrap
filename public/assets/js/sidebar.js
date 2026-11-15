// Define the current URL
let currentUrl = window.location.pathname.replace('/#!', ''); // Get the current page URL
if (currentUrl === '/')
  currentUrl = 'index.html';

// Function to find the active menu and its parents
const findActiveMenu = (layout = null) => {
  const mainMenu = document.querySelector('.main-menu');
  const menuLinks = mainMenu.querySelectorAll('.side-menu__item[href]'); // Select only menu items with href
  for (const menuLink of menuLinks) {
    const menuHref = menuLink.getAttribute('href'); // Get the href of the menu item
    // Check if the current URL matches the menu item's href
    if (currentUrl.includes(menuHref)) {
      menuLink.classList.add('active'); // Add 'active' class to the matching menu item

      // Open the parent dropdown if it exists
      let parentUl = menuLink.closest('.slide-menu');
      // Open all parent and add active class
      const mainLayout = document.documentElement.getAttribute('data-main-layout');
      openParentMenu(parentUl, mainLayout);

      // Stop searching once the active menu is found
      return;
    }
  }
};

function openParentMenu(parentUl, mainLayout) {
  if (parentUl) {
    const parentLi = parentUl.parentElement;
    if (mainLayout === 'vertical' || mainLayout === 'two-column')
      parentLi.classList.add('open-menu'); // Add 'active' class to the parent dropdown
    parentUl.previousElementSibling.classList.add('active');

    let parent2Ul = parentLi.closest('.slide-menu');;
    if (parent2Ul)
      openParentMenu(parent2Ul, mainLayout);
  }
}

// Smooth slide-down / slide-up for sub-menus (the template only toggled display).
const SLIDE_MS = 250;

function slideMenuOf(li) {
  return li.querySelector(':scope > .slide-menu');
}

function canAnimateSlide(menu) {
  return !!menu && typeof menu.animate === 'function'
    && document.documentElement.getAttribute('data-main-layout') !== 'horizontal'
    && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function openSlide(li) {
  const menu = slideMenuOf(li);
  const from = menu && menu._slideAnim ? menu.offsetHeight : 0; // reopening mid-close
  if (menu && menu._slideAnim) menu._slideAnim.cancel();
  li.classList.remove('slide-closing');
  li.classList.add('open-menu');
  if (!canAnimateSlide(menu)) return;
  menu.style.overflow = 'hidden';
  const anim = menu.animate(
    [{ height: from + 'px', opacity: from ? 1 : 0 }, { height: menu.scrollHeight + 'px', opacity: 1 }],
    { duration: SLIDE_MS, easing: 'cubic-bezier(.2, .7, .3, 1)' }
  );
  menu._slideAnim = anim;
  anim.onfinish = () => { menu.style.overflow = ''; menu._slideAnim = null; };
}

function closeSlide(li) {
  if (!li.classList.contains('open-menu')) return;
  const menu = slideMenuOf(li);
  if (!canAnimateSlide(menu) || !menu.offsetHeight) {
    li.classList.remove('open-menu', 'slide-closing');
    return;
  }
  const from = menu.offsetHeight;
  if (menu._slideAnim) menu._slideAnim.cancel();
  menu.style.overflow = 'hidden';
  li.classList.add('slide-closing'); // arrow turns back straight away
  const anim = menu.animate(
    [{ height: from + 'px', opacity: 1 }, { height: '0px', opacity: 0 }],
    { duration: SLIDE_MS - 30, easing: 'ease-in' }
  );
  menu._slideAnim = anim;
  anim.onfinish = () => {
    li.classList.remove('open-menu', 'slide-closing');
    menu.style.overflow = '';
    menu._slideAnim = null;
  };
}

function dropdownInit() {
  const mainMenu = document.querySelector('.main-menu');
  if (mainMenu) {
    const menuItemsSlide = document.querySelectorAll(".slide");

    // Loop through all menu items and check if they correspond to the current page
    menuItemsSlide.forEach((item) => {
      // layout.js calls dropdownInit() again after (re)initialising SimpleBar in the
      // vertical layout. Binding twice toggles the menu open and shut on one click,
      // so attach the handler only once per menu item.
      if (item.dataset.dropdownBound) return;
      item.dataset.dropdownBound = '1';

      item.addEventListener("click", function (e) {
        e.stopPropagation(); // Prevent event from bubbling up and triggering parent menu

        // Close all sibling slides at the same level
        const siblingSlides = item.parentElement.querySelectorAll(".slide");
        siblingSlides.forEach((sibling) => {
          if (sibling !== item) {
            closeSlide(sibling);
          }
        });

        // Toggle the current slide (expand/collapse submenu)
        if (document.documentElement.getAttribute('data-main-layout') === 'two-column')
          item.classList.add("open-menu");
        else if (item.classList.contains("open-menu") && !item.classList.contains("slide-closing"))
          closeSlide(item);
        else
          openSlide(item);
      });
    })
  }
}
document.addEventListener('DOMContentLoaded', () => {
  // Call the function to find and activate the menu
  findActiveMenu();
  dropdownInit();
});

window.addEventListener('load', () => {
  const container = document.querySelector('.main-menu');
  const activeElements = container.getElementsByClassName('active');

  if (activeElements.length > 0) {
    activeElements[activeElements.length - 1].scrollIntoView({
      behavior: 'smooth',
      block: 'center',
    });
  } else
    console.log('No active element found!');

  document.addEventListener('click', (event) => {
    setTimeout(() => {
      const clickedMenuItem = event.target.closest('.app-menu-item');

      if (!clickedMenuItem && document.documentElement.getAttribute('data-main-layout') !== 'vertical' && document.documentElement.getAttribute('data-main-layout') !== 'close-sidebar' && document.documentElement.getAttribute('data-main-layout') !== 'two-column') {

        // remove open menu class
        const appSidebarMenu = document.querySelector('.app-sidebar-menu');
        const openMenuItems = appSidebarMenu.getElementsByClassName('open-menu');
        for (let i = 0; i < openMenuItems.length; i++) {
          openMenuItems[i].classList.remove('open-menu');
        }
      }
    }, 100);
  });
});

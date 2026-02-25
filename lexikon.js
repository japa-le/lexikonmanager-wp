(function () {
  "use strict";

  const SELECTORS = {
    shortcodeSearch: "#shortcode-search",
    legacyDesktopAnchor: ".elementor-element[data-id='c12f1c9']",
    originalContainer: ".original-container",
    lexikonTitle: ".lexikontitel",
    tabButton: ".tabbutton",
    tabTitle: ".e-n-tab-title",
    alphabetBlock: ".alphabets",
    alphabetSearchBlock: ".alphabetsuche",
    searchInput: "#lexikon-search",
    searchListItems: ".lexikonsuche ul > li",
    stickyBlocks: ".lexikonsticky",
    searchBlocks: ".lexikonsuche",
    mobilePanel: "#mobilext",
    mobileToggler: "#mobilext-toggler",
    highlight: ".highlight",
    consumerContainer: "#verbraucherlex",
    regularContainer: "#regelex",
    companyContainer: "#firmlex",
  };

  const IDS = {
    highlightCount: "highlightCount",
    regelCount: "regelcount",
    verbraucherCount: "verbrauchercount",
    firmCount: "firmcount",
  };

  const state = {
    currentHighlightIndex: 0,
    scrollDown: true,
  };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function triggerSmallPageScroll() {
    if (window.scrollY === 0) {
      window.scrollBy(0, 1);
      state.scrollDown = true;
      return;
    }
    window.scrollBy(0, state.scrollDown ? 1 : -1);
    state.scrollDown = !state.scrollDown;
  }

  function isElementVisible(el) {
    const style = window.getComputedStyle(el);
    return style.display !== "none" && style.visibility !== "hidden";
  }

  function removeHighlights(element) {
    $$(SELECTORS.highlight, element).forEach((span) => {
      span.outerHTML = span.textContent;
    });
  }

  function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  function getTextNodes(element) {
    const nodes = [];

    function walk(node) {
      if (node.nodeType === Node.TEXT_NODE) {
        nodes.push(node);
        return;
      }
      node.childNodes.forEach(walk);
    }

    walk(element);
    return nodes;
  }

  function highlightByRegex(element, regex) {
    getTextNodes(element).forEach((node) => {
      const originalText = node.textContent;
      if (!regex.test(originalText)) return;

      const wrapped = document.createElement("span");
      wrapped.innerHTML = originalText.replace(regex, '<span class="highlight">$&</span>');
      node.parentNode.replaceChild(wrapped, node);
    });
  }

  function highlightWholeWord(element, word) {
    highlightByRegex(element, new RegExp(escapeRegExp(word), "gi"));
  }

  function hideEmptyAlphabets() {
    $$(SELECTORS.alphabetSearchBlock).forEach((block) => {
      const hasHighlight = $$("li", block).some((item) => $(SELECTORS.highlight, item));
      block.style.display = hasHighlight ? "block" : "none";
    });
  }

  function updateCount(el, value, withLeadingSpace = false) {
    if (!el) return;
    el.textContent = withLeadingSpace ? ` (${value})` : `(${value})`;
  }

  function countHighlights() {
    const visibleOuterHighlights = $$(SELECTORS.highlight).filter(
      (el) => !el.parentElement.closest(SELECTORS.highlight) && isElementVisible(el)
    );
    updateCount(document.getElementById(IDS.highlightCount), visibleOuterHighlights.length, false);

    const sections = [
      { selector: `${SELECTORS.regularContainer} .lexikonsuche ${SELECTORS.highlight}`, id: IDS.regelCount },
      { selector: `${SELECTORS.consumerContainer} .lexikonsuche ${SELECTORS.highlight}`, id: IDS.verbraucherCount },
      { selector: `${SELECTORS.companyContainer} .lexikonsuche ${SELECTORS.highlight}`, id: IDS.firmCount },
    ];

    sections.forEach(({ selector, id }) => {
      const count = $$(selector)
        .filter((el) => isElementVisible(el))
        .filter((el) => !el.parentElement.closest(SELECTORS.highlight)).length;
      updateCount(document.getElementById(id), count, true);
    });
  }

  function jumpToNextHighlight() {
    const highlights = $$(SELECTORS.highlight);
    if (!highlights.length) return;

    if (state.currentHighlightIndex > 0) {
      highlights[state.currentHighlightIndex - 1].classList.remove("current-highlight");
    }

    state.currentHighlightIndex = (state.currentHighlightIndex + 1) % highlights.length;
    const current = highlights[state.currentHighlightIndex];
    current.classList.add("current-highlight");
    current.scrollIntoView({ behavior: "smooth", block: "center" });
  }

  function showTabContent(letter, buttons) {
    $$(SELECTORS.alphabetBlock).forEach((section) => {
      section.style.display = "none";
    });

    $$(`${SELECTORS.alphabetBlock} .${letter}-text`).forEach((selected) => {
      const block = selected.closest(SELECTORS.alphabetBlock);
      if (block) block.style.display = "block";
    });

    buttons.forEach((btn) => btn.classList.remove("active"));
    const activeButton = document.querySelector(`#${letter}-tab`);
    if (activeButton) activeButton.classList.add("active");
  }

  function moveSearchBox() {
    const searchElement = $(SELECTORS.shortcodeSearch);
    const h1Container = $(SELECTORS.legacyDesktopAnchor);

    if (searchElement && h1Container) {
      if (window.innerWidth <= 767) {
        const next = h1Container.nextElementSibling;
        if (!next || next.id !== "shortcode-search") {
          h1Container.parentNode.insertBefore(searchElement, h1Container.nextSibling);
        }
      } else {
        const originalContainer = $(SELECTORS.originalContainer);
        if (originalContainer && !originalContainer.contains(searchElement)) {
          originalContainer.appendChild(searchElement);
        }
      }
    }
  }

  function simulateTyping(searchField, text) {
    if (!searchField) return;

    searchField.focus();
    searchField.value = "";

    let index = 0;
    function typeNextCharacter() {
      if (index >= text.length) return;

      const char = text[index];
      searchField.value += char;
      searchField.dispatchEvent(new KeyboardEvent("keydown", { key: char, bubbles: true }));
      searchField.dispatchEvent(new Event("input", { bubbles: true }));
      searchField.dispatchEvent(new KeyboardEvent("keyup", { key: char, bubbles: true }));

      index += 1;
      setTimeout(typeNextCharacter, 50);
    }

    typeNextCharacter();
  }

  function handleSearch() {
    const searchInput = $(SELECTORS.searchInput);
    if (!searchInput) return;

    const searchTerm = searchInput.value.toLowerCase().trim();
    const listItems = $$(SELECTORS.searchListItems);
    const countElement = document.getElementById(IDS.highlightCount);
    const regelCountElement = document.getElementById(IDS.regelCount);
    const verbraucherCountElement = document.getElementById(IDS.verbraucherCount);
    const firmCountElement = document.getElementById(IDS.firmCount);
    const lexikonSticky = $$(SELECTORS.stickyBlocks);
    const lexiSuche = $$(SELECTORS.searchBlocks);
    const toggler = $(SELECTORS.mobileToggler);
    const isDesktop = window.matchMedia("(min-width: 767px)").matches;

    function hideToggler() {
      if (toggler) toggler.style.display = "none";
    }

    function showToggler() {
      if (toggler && searchTerm === "") toggler.style.display = "block";
    }

    if (searchTerm === "") {
      setTimeout(triggerSmallPageScroll, 10);

      $$(
        `${SELECTORS.consumerContainer}, ${SELECTORS.regularContainer}, ${SELECTORS.companyContainer}`
      ).forEach((el) => {
        el.style.width = "";
        el.style.marginLeft = "";
      });

      [countElement, regelCountElement, verbraucherCountElement, firmCountElement].forEach((el) => {
        if (!el) return;
        el.style.display = "none";
        el.textContent = "";
      });

      showToggler();

      lexiSuche.forEach((el) => {
        el.style.display = "none";
      });
      lexikonSticky.forEach((el) => {
        el.style.display = "flex";
      });

      $$(SELECTORS.tabButton).forEach((btn) => {
        btn.style.display = "block";
      });

      listItems.forEach((item) => {
        item.style.display = "list-item";
        removeHighlights(item);
      });

      hideEmptyAlphabets();
      return;
    }

    [countElement, regelCountElement, verbraucherCountElement, firmCountElement].forEach((el) => {
      if (el) el.style.display = "block";
    });

    hideToggler();

    lexiSuche.forEach((el) => {
      el.style.display = "block";
    });
    lexikonSticky.forEach((el) => {
      el.style.display = "none";
    });

    $$(SELECTORS.tabButton).forEach((btn) => {
      btn.style.display = "none";
    });

    const targetContainers = $$(`${SELECTORS.consumerContainer}, ${SELECTORS.regularContainer}, ${SELECTORS.companyContainer}`);
    if (isDesktop) {
      targetContainers.forEach((el) => {
        el.style.setProperty("width", "69vw", "important");
        el.style.marginLeft = "-281px";
      });
    } else {
      targetContainers.forEach((el) => {
        el.style.width = "";
        el.style.marginLeft = "";
      });
    }

    listItems.forEach((item) => removeHighlights(item));

    listItems.forEach((item) => {
      const contentText = item.textContent.toLowerCase();
      if (contentText.includes(searchTerm)) {
        item.style.display = "list-item";
        highlightWholeWord(item, searchTerm);
      } else {
        item.style.display = "none";
      }
    });

    hideEmptyAlphabets();
    setTimeout(countHighlights, 10);
  }

  function initJumpButtons(searchInput) {
    const mappings = [
      ["jump-to-tab-i", "Insolvenzverwalter"],
      ["jump-to-tab-a", "Aussergerichtlicher Einigungsversuch"],
      ["jump-to-tab-f", "Flexibler Nullplan"],
      ["jump-to-tab-e", "Einmalzahlung"],
      ["jump-to-tab-r", "Ratenzahlung"],
    ];

    mappings.forEach(([id, text]) => {
      const btn = document.getElementById(id);
      if (!btn) return;
      btn.addEventListener("click", (event) => {
        event.preventDefault();
        simulateTyping(searchInput, text);
      });
    });
  }

  function initMobileInteractions(searchInput) {
    if (!window.matchMedia("(max-width: 767px)").matches) return;

    const floatingContainer = $(SELECTORS.mobilePanel);
    const toggler = $(SELECTORS.mobileToggler);
    const floatingButton = $(SELECTORS.mobilePanel);

    if (!floatingContainer || !toggler || !floatingButton) return;

    const searchBox = $(SELECTORS.shortcodeSearch);
    const lexikonTitle = $(SELECTORS.lexikonTitle);
    if (searchBox && lexikonTitle) {
      lexikonTitle.parentNode.insertBefore(searchBox, lexikonTitle.nextSibling);
    }

    function isSearchActive() {
      return !!(searchInput && searchInput.value.trim() !== "");
    }

    floatingButton.addEventListener("click", (event) => {
      event.stopPropagation();
      floatingButton.classList.toggle("expanded");
    });

    document.addEventListener("click", (event) => {
      if (!floatingButton.contains(event.target)) {
        floatingButton.classList.remove("expanded");
      }
    });

    toggler.addEventListener("click", (event) => {
      event.stopPropagation();
      floatingContainer.style.display = "flex";
      setTimeout(() => floatingContainer.classList.add("expanded"), 10);
      toggler.style.display = "none";
    });

    $$("#mobilext .tabbutton").forEach((button) => {
      button.addEventListener("click", (event) => {
        event.stopPropagation();
      });
    });

    document.addEventListener("click", (event) => {
      if (!floatingContainer.contains(event.target) && !toggler.contains(event.target)) {
        floatingContainer.classList.remove("expanded");
        setTimeout(() => {
          floatingContainer.style.display = "none";
          if (!isSearchActive()) {
            toggler.style.display = "block";
          }
        }, 300);
      }
    });

    if (searchInput) {
      searchInput.addEventListener("input", () => {
        toggler.style.display = isSearchActive() ? "none" : "block";
      });
    }
  }

  function initUrlDrivenSearch(searchInput) {
    if (!searchInput) return;

    const url = new URL(window.location.href);
    let term = url.searchParams.get("begriff") || url.searchParams.get("q");
    let slug = null;

    if (!term) {
      const match = url.pathname.match(/\/lexikon\/([^\/]+)\/?$/);
      if (match && match[1]) {
        slug = decodeURIComponent(match[1]);
        term = slug;
      }
    } else {
      term = decodeURIComponent(term);
    }

    if (!term) return;

    term = term
      .replace(/-/g, " ")
      .replace(/\s+/g, " ")
      .trim()
      .replace(/\b\w/g, (l) => l.toUpperCase());

    const letter = term.charAt(0).toLowerCase();
    const tabBtn = document.getElementById(`${letter}-tab`);
    if (tabBtn) tabBtn.click();

    searchInput.value = term;
    searchInput.dispatchEvent(new Event("input", { bubbles: true }));
    handleSearch();

    setTimeout(() => {
      let targetLi = null;

      if (slug) {
        const link = document.querySelector(`.lexikonsuche a[href*="/${slug}"]`);
        if (link) targetLi = link.closest("li");
      }

      if (!targetLi) {
        const needle = term.toLowerCase();
        targetLi = $$(SELECTORS.searchListItems).find((li) => li.textContent.toLowerCase().includes(needle));
      }

      if (targetLi) {
        targetLi.scrollIntoView({ behavior: "smooth", block: "center" });
        targetLi.classList.add("current-highlight");
      }
    }, 300);
  }

  function init() {
    moveSearchBox();
    window.addEventListener("resize", moveSearchBox);

    const tabButtons = $$(SELECTORS.tabButton);
    tabButtons.forEach((button) => {
      button.addEventListener("click", (event) => {
        event.preventDefault();
        const letter = button.id.replace("-tab", "");
        showTabContent(letter, tabButtons);
      });
    });
    showTabContent("a", tabButtons);

    $$(SELECTORS.tabTitle).forEach((tab) => {
      tab.addEventListener("click", () => setTimeout(triggerSmallPageScroll, 10));
    });

    const searchInput = $(SELECTORS.searchInput);
    const countElement = document.getElementById(IDS.highlightCount);
    const regelCountElement = document.getElementById(IDS.regelCount);
    const verbraucherCountElement = document.getElementById(IDS.verbraucherCount);
    [countElement, regelCountElement, verbraucherCountElement].forEach((el) => {
      if (el) el.style.display = "none";
    });

    if (searchInput) {
      searchInput.addEventListener("keyup", (e) => {
        if (e.key !== "Enter") handleSearch();
      });

      searchInput.addEventListener("keydown", (e) => {
        if (e.key !== "Enter") return;
        e.preventDefault();
        jumpToNextHighlight();
      });
    }

    initJumpButtons(searchInput);
    initMobileInteractions(searchInput);
    initUrlDrivenSearch(searchInput);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();

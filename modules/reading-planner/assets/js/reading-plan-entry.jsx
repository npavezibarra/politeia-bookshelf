import { createRoot } from "react-dom/client";
import ReadingPlanApp from "./ReadingPlanApp";
import "../css/reading-plan-app.css";

console.log("[ReadingPlan] entry file loaded");

let root = null;

const lockBodyScroll = () => {
  document.body.style.overflow = "hidden";
};

const unlockBodyScroll = () => {
  document.body.style.overflow = "";
};

const closeModal = () => {
  const modal = document.getElementById("politeia-reading-plan-root");
  if (root) {
    root.unmount();
    root = null;
  }
  if (modal) {
    modal.classList.remove("is-open");
  }
  unlockBodyScroll();
};

const openModal = () => {
  console.log("[ReadingPlan] opening modal");
  const modal = document.getElementById("politeia-reading-plan-root");
  if (!modal) {
    if (window.PoliteiaReadingPlan?.debug) {
      console.warn("[ReadingPlan] Modal root not found.");
    }
    return;
  }
  const appConfig = window.PoliteiaReadingPlan || {};
  if (!appConfig.restUrl || !appConfig.nonce || !appConfig.userId) {
    if (appConfig.debug) {
      console.warn("[ReadingPlan] Missing REST config.");
    }
    return;
  }

  modal.classList.add("politeia-reading-plan-modal", "is-open");
  lockBodyScroll();

  if (!root) {
    root = createRoot(modal);
  }

  console.log("[ReadingPlan] mounting React app");
  root.render(
    <div className="politeia-reading-plan__panel">
      <ReadingPlanApp
        restUrl={appConfig.restUrl}
        nonce={appConfig.nonce}
        userId={appConfig.userId}
        onClose={closeModal}
      />
    </div>
  );
};

const bindTrigger = () => {
  const trigger = document.getElementById("politeia-open-reading-plan");
  const modalRoot = document.getElementById("politeia-reading-plan-root");
  if (!trigger || !modalRoot) {
    console.warn("[ReadingPlan] required DOM elements not found");
    return;
  }

  trigger.addEventListener("click", (event) => {
    event.preventDefault();
    console.log("[ReadingPlan] open button clicked");
    openModal();
  });
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", bindTrigger);
} else {
  bindTrigger();
}

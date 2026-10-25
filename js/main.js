(function () {
  const modal = document.getElementById("bookingModal");
  const closeButton = modal.querySelector(".modal-close");
  const triggers = document.querySelectorAll(".book-trigger");
  const navToggle = document.querySelector(".nav-toggle");
  const navLinks = document.querySelector(".nav-links");

  const openModal = (event) => {
    if (event) event.preventDefault();
    modal.classList.add("active");
    modal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  };

  const closeModal = () => {
    modal.classList.remove("active");
    modal.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  };

  triggers.forEach((trigger) => trigger.addEventListener("click", openModal));
  closeButton.addEventListener("click", closeModal);

  modal.addEventListener("click", (event) => {
    if (event.target === modal) closeModal();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeModal();
      if (navLinks.classList.contains("open")) {
        navToggle.setAttribute("aria-expanded", "false");
        navLinks.classList.remove("open");
      }
    }
  });

  if (navToggle && navLinks) {
    navToggle.addEventListener("click", () => {
      const isOpen = navLinks.classList.toggle("open");
      navToggle.setAttribute("aria-expanded", String(isOpen));
    });

    navLinks.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        navLinks.classList.remove("open");
        navToggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  const bookingForm = document.getElementById("bookingForm");
  const bookingError = document.getElementById("bookingFormError");
  const bookingSuccess = document.getElementById("bookingFormSuccess");
  const bookingSubmit = document.getElementById("bookingSubmit");

  const resetBookingFeedback = () => {
    if (bookingError) {
      bookingError.hidden = true;
      bookingError.textContent = "";
    }
    if (bookingSuccess) {
      bookingSuccess.hidden = true;
      bookingSuccess.textContent = "";
    }
  };

  const trustStrip = document.querySelector(".trust");
  if (trustStrip) {
    trustStrip.classList.add("trust--motion");
    const revealTrust = () => trustStrip.classList.add("is-visible");
    if ("IntersectionObserver" in window) {
      const trustObserver = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              revealTrust();
              trustObserver.disconnect();
            }
          });
        },
        { threshold: 0.12, rootMargin: "0px 0px -8% 0px" }
      );
      trustObserver.observe(trustStrip);
    } else {
      revealTrust();
    }
  }

  if (bookingForm && bookingSubmit) {
    bookingForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      resetBookingFeedback();

      if (!bookingForm.reportValidity()) {
        return;
      }

      bookingSubmit.disabled = true;
      bookingSubmit.textContent = "Sending…";

      try {
        const response = await fetch("api/book-session.php", {
          method: "POST",
          body: new FormData(bookingForm),
        });
        const data = await response.json();

        if (!response.ok || !data.ok) {
          const msg = data.detail
            ? `${data.error}\n\n(${data.detail})`
            : data.error || "Something went wrong. Please try again.";
          throw new Error(msg);
        }

        if (bookingSuccess) {
          bookingSuccess.textContent = data.message || "Thank you. We will get back to you soon.";
          bookingSuccess.hidden = false;
        }
        bookingForm.reset();
      } catch (err) {
        if (bookingError) {
          bookingError.textContent = err.message || "Could not send your request.";
          bookingError.hidden = false;
        }
      } finally {
        bookingSubmit.disabled = false;
        bookingSubmit.textContent = "Send request";
      }
    });
  }
})();

(function () {
  var page = document.body.getAttribute("data-page");
  var navLinks = document.querySelectorAll(".site-nav a");
  var config = window.API_CONFIG || {};

  navLinks.forEach(function (link) {
    var href = link.getAttribute("href") || "";
    if ((page === "home" && href === "index.html") || href.indexOf(page + ".html") !== -1) {
      link.classList.add("is-active");
    }
  });

  var revealTargets = document.querySelectorAll(".panel, .page-hero, .hero, .tile, .case-row, .service-bands article, .news-columns article");
  revealTargets.forEach(function (el) {
    el.setAttribute("data-reveal", "true");
  });

  if ("IntersectionObserver" in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
        }
      });
    }, { threshold: 0.12 });

    revealTargets.forEach(function (el) { observer.observe(el); });
  } else {
    revealTargets.forEach(function (el) { el.classList.add("is-visible"); });
  }

  var form = document.getElementById("contact-form");
  var resultEl = document.getElementById("contact-result");
  if (form && resultEl) {
    form.addEventListener("submit", async function (event) {
      event.preventDefault();
      var formData = new FormData(form);
      var payload = {
        name: String(formData.get("name") || ""),
        company: String(formData.get("company") || ""),
        phone: String(formData.get("phone") || ""),
        email: String(formData.get("email") || ""),
        budget_range: String(formData.get("budget_range") || ""),
        source_page: window.location.pathname.split("/").pop() || "contact.html",
        message: String(formData.get("message") || "")
      };

      if (!config.domain || !config.apiKey) {
        resultEl.textContent = "当前是演示模式。插件安装到项目后会自动注入 API 配置。";
        return;
      }

      try {
        var response = await fetch(config.domain + "/api/function/companysite_contact_submit", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Authorization": "Bearer " + config.apiKey
          },
          body: JSON.stringify(payload)
        });
        var data = await response.json();
        resultEl.textContent = data.message || "提交成功";
        if (response.ok) {
          form.reset();
        }
      } catch (error) {
        resultEl.textContent = "提交失败，请稍后重试。";
        console.error(error);
      }
    });
  }

  var newsList = document.getElementById("news-list");
  var caseList = document.querySelector(".case-list");
  var caseFeatureLink = document.getElementById("case-feature-link");
  var newsFeatureLink = document.getElementById("news-feature-link");
  var params = new URLSearchParams(window.location.search);
  var detailId = Number(params.get("id") || 0);

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function postFunction(slug, payload) {
    return fetch(config.domain + "/api/function/" + slug, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": "Bearer " + config.apiKey
      },
      body: JSON.stringify(payload || {})
    }).then(function (response) { return response.json(); });
  }

  if (caseList && config.domain && config.apiKey) {
    postFunction("companysite_case_list", { page: 1, page_size: 6 })
      .then(function (data) {
        if (!data.items || !Array.isArray(data.items) || !data.items.length) {
          return;
        }
        if (caseFeatureLink && data.items[0] && data.items[0].id) {
          caseFeatureLink.href = "case-detail.html?id=" + data.items[0].id;
        }
        caseList.innerHTML = data.items.map(function (item) {
          return "<article class=\"case-row\"><div><p class=\"case-meta\">" + escapeHtml(item.industry || "案例项目") + "</p><h3>" + escapeHtml(item.title || "") + "</h3></div><p>" + escapeHtml(item.result_summary || "") + "</p><a class=\"detail-link\" href=\"case-detail.html?id=" + item.id + "\">查看详情</a></article>";
        }).join("");
      })
      .catch(function () {});
  }

  if (newsList && config.domain && config.apiKey) {
    postFunction("companysite_news_list", { page: 1, page_size: 4 })
      .then(function (data) {
        if (!data.items || !Array.isArray(data.items) || !data.items.length) {
          return;
        }
        if (newsFeatureLink && data.items[0] && data.items[0].id) {
          newsFeatureLink.href = "news-detail.html?id=" + data.items[0].id;
        }
        newsList.innerHTML = data.items.map(function (item) {
          return "<article><p>" + escapeHtml(item.publish_date || "") + "</p><h3>" + escapeHtml(item.title || "") + "</h3><p>" + escapeHtml(item.excerpt || "") + "</p><a class=\"detail-link\" href=\"news-detail.html?id=" + item.id + "\">阅读全文</a></article>";
        }).join("");
      })
      .catch(function () {});
  }

  if (document.getElementById("news-detail-title") && config.domain && config.apiKey && detailId > 0) {
    postFunction("companysite_news_detail", { id: detailId })
      .then(function (data) {
        var item = data.item;
        if (!item) { return; }
        document.getElementById("news-detail-title").textContent = item.title || "";
        document.getElementById("news-detail-excerpt").textContent = item.excerpt || "";
        document.getElementById("news-detail-date").textContent = item.publish_date || "";
        document.getElementById("news-detail-author").textContent = item.author || "";
        document.getElementById("news-detail-content").innerHTML = item.content || "";
      })
      .catch(function () {});
  }

  if (document.getElementById("case-detail-title") && config.domain && config.apiKey && detailId > 0) {
    postFunction("companysite_case_detail", { id: detailId })
      .then(function (data) {
        var item = data.item;
        if (!item) { return; }
        document.getElementById("case-detail-title").textContent = item.title || "";
        document.getElementById("case-detail-subtitle").textContent = item.result_summary || "";
        document.getElementById("case-detail-client").textContent = item.client_name || "";
        document.getElementById("case-detail-industry").textContent = item.industry || "";
        document.getElementById("case-detail-content").innerHTML = item.content || "";
      })
      .catch(function () {});
  }
}());

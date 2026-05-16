(function () {
  var app = document.getElementById("app");

  function api(path, params, options) {
    var url = new URL("/api/spa/" + path + ".php", window.location.origin);
    Object.keys(params || {}).forEach(function (key) {
      if (params[key] !== undefined && params[key] !== null) {
        url.searchParams.set(key, params[key]);
      }
    });

    return fetch(url.toString(), options || {}).then(function (response) {
      return response.json().then(function (data) {
        data.http_status = response.status;
        return data;
      });
    });
  }

  function routeParts() {
    var body = window.location.pathname.replace(/^\/spa\/?/, "");
    var route = body.split("/").filter(Boolean);
    var params = new URLSearchParams(window.location.search);
    return { route: route, params: params };
  }

  function setActiveRoute() {
    var current = window.location.pathname;
    document.querySelectorAll(".nav a").forEach(function (link) {
      var href = new URL(link.href, window.location.origin);
      var active = href.pathname === current;
      link.toggleAttribute("aria-current", active);
    });
  }

  function navigate(url) {
    window.history.pushState(null, "", url);
    router();
  }

  function decodeRouteValue(value, fallback) {
    try {
      return decodeURIComponent(value || fallback);
    } catch (e) {
      return fallback;
    }
  }

  function header(title, text, badges) {
    return (
      '<div class="section-head">' +
      "<div><h1>" +
      title +
      "</h1><p>" +
      text +
      "</p></div>" +
      '<div class="badge-row">' +
      (badges || [])
        .map(function (badge) {
          return '<span class="badge">' + badge + "</span>";
        })
        .join("") +
      "</div></div>"
    );
  }

  function showLoading(title) {
    app.innerHTML =
      header(title, "Waiting for JSON data from the PHP API.", []) +
      '<div class="content"><div class="loading">Loading...</div></div>';
  }

  function showError(title, data) {
    var detail = data && (data.db_error || data.error || data.message);
    var sql = data && data.sql ? "\n\nSQL:\n" + data.sql : "";
    app.innerHTML =
      header(title, "The API returned an error response.", ["JSON API"]) +
      '<div class="content"><div class="error">' +
      (detail || "Unknown error") +
      sql +
      "</div></div>";
  }

  function renderSearch() {
    var parsed = routeParts();
    var q = parsed.params.get("q") || "";
    var sort = parsed.params.get("sort") || "newest";

    app.innerHTML =
      header("SPA Search", "Search results are loaded from JSON and rendered in the browser.", [
        "SQLi JSON API",
        "Reflected DOM XSS",
        "Stored XSS Seed"
      ]) +
      '<div class="content">' +
      '<form id="search-form" class="toolbar">' +
      '<input id="search-q" name="q" value="' +
      q.replace(/"/g, "&quot;") +
      '" placeholder="Search news, tags, content" autocomplete="off" />' +
      '<select id="search-sort" name="sort">' +
      '<option value="newest">Newest</option>' +
      '<option value="views">Most viewed</option>' +
      "</select>" +
      "<button>Search</button>" +
      "</form>" +
      '<div id="search-output"><div class="loading">Loading...</div></div>' +
      "</div>";

    document.getElementById("search-sort").value = sort;
    document.getElementById("search-form").addEventListener("submit", function (event) {
      event.preventDefault();
      var nextQ = document.getElementById("search-q").value;
      var nextSort = document.getElementById("search-sort").value;
      navigate("/spa/search?q=" + encodeURIComponent(nextQ) + "&sort=" + encodeURIComponent(nextSort));
    });

    api("search", { q: q, sort: sort }).then(function (data) {
      var output = document.getElementById("search-output");
      if (!data.ok) {
        output.innerHTML = '<div class="error">' + (data.db_error || data.error || "Search failed") + "</div>";
        return;
      }

      var rows = data.results || [];
      var html =
        '<div class="notice">Query: <strong>' +
        data.query +
        "</strong> | Results: <strong>" +
        data.count +
        "</strong></div>";

      if (!rows.length) {
        html += '<div class="empty">No matching articles.</div>';
      } else {
        html += rows
          .map(function (item) {
            // [VULN] API data is intentionally rendered with innerHTML.
            return (
              '<article class="item">' +
              '<h2><a href="/spa/article/' +
              item.id +
              '">' +
              item.title +
              "</a></h2>" +
              '<div class="meta">' +
              item.cat_name +
              " | " +
              item.username +
              " | " +
              item.views +
              " views</div>" +
              '<div class="summary">' +
              item.summary +
              "</div>" +
              "</article>"
            );
          })
          .join("");
      }

      output.innerHTML = html;
    });
  }

  function renderArticle(id) {
    showLoading("SPA Article");

    api("news", { id: id }).then(function (data) {
      if (!data.ok) {
        showError("SPA Article", data);
        return;
      }

      var article = data.article;
      // [VULN] Article fields are intentionally rendered with innerHTML.
      app.innerHTML =
        header("SPA Article", "The selected article is fetched from a JSON endpoint.", [
          "SQLi UNION API",
          "DOM XSS Sink"
        ]) +
        '<div class="content">' +
        "<h2>" +
        article.title +
        "</h2>" +
        '<div class="meta">' +
        article.cat_name +
        " | " +
        article.username +
        " | " +
        article.views +
        " views</div>" +
        '<div class="article-body">' +
        article.content +
        "</div>" +
        '<p><a class="button" href="/spa/comments/' +
        article.id +
        '">Open comments</a></p>' +
        "</div>";
    });
  }

  function renderComments(newsId) {
    showLoading("SPA Comments");

    api("comments", { news_id: newsId }).then(function (data) {
      if (!data.ok) {
        showError("SPA Comments", data);
        return;
      }

      var comments = data.comments || [];
      var commentHtml = comments.length
        ? comments
            .map(function (comment) {
              // [VULN] Stored comment content is intentionally rendered with innerHTML.
              return (
                '<div class="comment">' +
                '<div><span class="comment-author">' +
                (comment.username || comment.author_name || "Guest") +
                '</span> <span class="meta">' +
                comment.created_at +
                "</span></div>" +
                '<div class="comment-body">' +
                comment.content +
                "</div>" +
                "</div>"
              );
            })
            .join("")
        : '<div class="empty">No comments for this article.</div>';

      app.innerHTML =
        header("SPA Comments", "Comments are loaded and created through JSON-style endpoints.", [
          "Stored XSS",
          "SQLi JSON API"
        ]) +
        '<div class="content">' +
        '<div class="notice">Article ID: <strong>' +
        data.news_id +
        "</strong></div>" +
        '<div id="comment-list">' +
        commentHtml +
        "</div>" +
        '<div style="margin-top:18px;padding-top:14px;border-top:1px solid #e4e9ee;">' +
        "<h3>Add comment</h3>" +
        '<form id="comment-form" class="form-stack">' +
        '<input name="news_id" value="' +
        newsId +
        '" placeholder="Article ID" />' +
        '<input name="author_name" value="spa_guest" placeholder="Display name" />' +
        '<textarea name="content" placeholder="Comment body"></textarea>' +
        "<button>Submit</button>" +
        "</form>" +
        "</div>" +
        "</div>";

      document.getElementById("comment-form").addEventListener("submit", function (event) {
        event.preventDefault();
        var form = new FormData(event.currentTarget);
        api("comment_add", {}, { method: "POST", body: form }).then(function (result) {
          if (!result.ok) {
            document.getElementById("comment-list").insertAdjacentHTML(
              "beforebegin",
              '<div class="error">' + (result.db_error || result.error || "Insert failed") + "</div>"
            );
            return;
          }
          navigate("/spa/comments/" + encodeURIComponent(form.get("news_id")) + "?saved=" + Date.now());
        });
      });
    });
  }

  function renderLogs() {
    var parsed = routeParts();
    var keyword = parsed.params.get("keyword") || "";

    app.innerHTML =
      header("SPA Search Logs", "Search log rows are loaded from an API and rendered client-side.", [
        "Stored XSS",
        "SQLi JSON API"
      ]) +
      '<div class="content">' +
      '<form id="logs-form" class="toolbar">' +
      '<input id="logs-keyword" value="' +
      keyword.replace(/"/g, "&quot;") +
      '" placeholder="Filter log keyword" autocomplete="off" />' +
      '<span></span>' +
      "<button>Filter</button>" +
      "</form>" +
      '<div id="logs-output"><div class="loading">Loading...</div></div>' +
      "</div>";

    document.getElementById("logs-form").addEventListener("submit", function (event) {
      event.preventDefault();
      var next = document.getElementById("logs-keyword").value;
      navigate("/spa/logs?keyword=" + encodeURIComponent(next));
    });

    api("logs", { keyword: keyword }).then(function (data) {
      var output = document.getElementById("logs-output");
      if (!data.ok) {
        output.innerHTML = '<div class="error">' + (data.db_error || data.error || "Log query failed") + "</div>";
        return;
      }

      var rows = data.logs || [];
      var html =
        '<table class="log-table"><thead><tr><th>Keyword</th><th>IP</th><th>User agent</th><th>Created</th></tr></thead><tbody>';
      html += rows.length
        ? rows
            .map(function (row) {
              // [VULN] Search log data is intentionally rendered with innerHTML.
              return (
                "<tr><td>" +
                row.keyword +
                "</td><td>" +
                row.ip_address +
                "</td><td>" +
                row.user_agent +
                "</td><td>" +
                row.created_at +
                "</td></tr>"
              );
            })
            .join("")
        : '<tr><td colspan="4">No log rows.</td></tr>';
      html += "</tbody></table>";
      output.innerHTML = html;
    });
  }

  function router() {
    setActiveRoute();
    var parsed = routeParts();
    var route = parsed.route[0] || "search";

    if (route === "article") {
      renderArticle(decodeRouteValue(parsed.route[1], "1"));
    } else if (route === "comments") {
      renderComments(decodeRouteValue(parsed.route[1], "1"));
    } else if (route === "logs") {
      renderLogs();
    } else {
      renderSearch();
    }
  }

  document.addEventListener("click", function (event) {
    var link = event.target.closest("a[href]");
    if (!link) return;

    var url = new URL(link.href, window.location.origin);
    if (url.origin === window.location.origin && url.pathname.indexOf("/spa/") === 0) {
      event.preventDefault();
      navigate(url.pathname + url.search);
    }
  });

  window.addEventListener("popstate", router);
  router();
})();

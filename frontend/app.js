const status = document.querySelector("#api-status");

fetch("/api/health")
  .then((response) => {
    if (!response.ok) {
      throw new Error(`API returned ${response.status}`);
    }
    return response.json();
  })
  .then((data) => {
    status.textContent = `${data.service} is online.`;
  })
  .catch((error) => {
    status.textContent = `API unavailable: ${error.message}`;
  });

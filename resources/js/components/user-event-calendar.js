import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import itLocale from '@fullcalendar/core/locales/it';

const calendarEl = document.getElementById('user-event-calendar');
const dayEventsContainer = document.getElementById('user-event-calendar-day-events');
const dayEventsTitle = document.getElementById('user-event-calendar-day-events-title');
const dayEventsList = document.getElementById('user-event-calendar-day-events-list');
const selectedDayClassName = 'is-selected-day';

function formatDateKey(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

function formatDayLabel(dateKey) {
  const [year, month, day] = dateKey.split('-').map(Number);
  const date = new Date(year, month - 1, day);

  return new Intl.DateTimeFormat('it-IT', {
    day: 'numeric',
    month: 'long',
  }).format(date);
}

function formatTimeRange(event) {
  const start = event.start ? new Date(event.start) : null;
  const end = event.end ? new Date(event.end) : null;

  if (!start) {
    return '';
  }

  const formatter = new Intl.DateTimeFormat('it-IT', {
    hour: '2-digit',
    minute: '2-digit',
  });

  if (!end) {
    return formatter.format(start);
  }

  return `${formatter.format(start)} - ${formatter.format(end)}`;
}

function typeLabel(type) {
  if (type === 'live') {
    return calendarEl.dataset.typeLive;
  }

  if (type === 'res') {
    return calendarEl.dataset.typeRes;
  }

  return type;
}

function renderDayEvents(dateKey, eventsByDay) {
  const events = eventsByDay.get(dateKey) ?? [];

  if (events.length === 0) {
    dayEventsContainer.classList.add('hidden');
    dayEventsTitle.textContent = '';
    dayEventsList.innerHTML = '';

    return;
  }

  const titleTemplate = events.length === 1
    ? calendarEl.dataset.listTitleSingular
    : calendarEl.dataset.listTitlePlural;

  dayEventsTitle.textContent = titleTemplate
    .replace(':count', String(events.length))
    .replace(':date', formatDayLabel(dateKey));

  dayEventsList.innerHTML = events.map((event) => {
    const timeRange = formatTimeRange(event);
    const courseTitle = event.extendedProps?.course_title ?? '';
    const className = event.extendedProps?.class_name ?? '';
    const type = typeLabel(event.extendedProps?.type ?? '');

    return `
      <article class="rounded-box bg-base-100 px-4 py-3 shadow-sm">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h3 class="truncate text-sm font-semibold text-base-content sm:text-base">${event.title}</h3>
            <p class="mt-1 text-xs text-base-content/70 sm:text-sm">${courseTitle}</p>
            <p class="mt-1 text-xs text-base-content/60 sm:text-sm">${className}${timeRange ? ` • ${timeRange}` : ''}</p>
          </div>
          <span class="badge badge-primary badge-outline shrink-0">${type}</span>
        </div>
      </article>
    `;
  }).join('');

  dayEventsContainer.classList.remove('hidden');
}

function markSelectedDay(dateKey) {
  const selectedDays = calendarEl.querySelectorAll(`.${selectedDayClassName}`);

  selectedDays.forEach((day) => {
    day.classList.remove(selectedDayClassName);
  });

  const activeDay = calendarEl.querySelector(`td[data-date="${dateKey}"]`);

  if (activeDay instanceof HTMLElement) {
    activeDay.classList.add(selectedDayClassName);
  }
}

async function bootstrapCalendar() {
  const eventsUrl = calendarEl.dataset.eventsUrl;

  try {
    const response = await fetch(eventsUrl, {
      headers: {
        Accept: 'application/json',
      },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error(`Unexpected response: ${response.status}`);
    }

    const payload = await response.json();
    const events = Array.isArray(payload.events) ? payload.events : [];
    const eventsByDay = new Map();

    events.forEach((event) => {
      const dateKey = event.start ? event.start.slice(0, 10) : null;

      if (!dateKey) {
        return;
      }

      const groupedEvents = eventsByDay.get(dateKey) ?? [];
      groupedEvents.push(event);
      eventsByDay.set(dateKey, groupedEvents);
    });

    const calendar = new Calendar(calendarEl, {
      plugins: [dayGridPlugin],
      initialView: 'dayGridMonth',
      locale: itLocale,
      height: 'auto',
      contentHeight: 'auto',
      expandRows: false,
      dayMaxEventRows: 1,
      fixedWeekCount: false,
      showNonCurrentDates: false,
      events,
      headerToolbar: {
        start: '',
        center: '',
        end: 'prev title next',
      },
      buttonText: {
        prev: '',
        next: '',
      },
      dayCellDidMount(info) {
        info.el.style.cursor = 'pointer';
      },
      eventClick(info) {
        renderDayEvents(info.event.startStr.slice(0, 10), eventsByDay);
      },
    });

    calendar.render();

    calendarEl.addEventListener('click', (event) => {
      const dayCell = event.target.closest('td[data-date]');

      if (!(dayCell instanceof HTMLElement)) {
        return;
      }

      const { date } = dayCell.dataset;

      if (!date) {
        return;
      }

      markSelectedDay(date);
      renderDayEvents(date, eventsByDay);
    });
  } catch (error) {
    dayEventsContainer.classList.remove('hidden');
    dayEventsTitle.textContent = calendarEl.dataset.errorLabel;
    dayEventsList.innerHTML = '';
    console.error(error);
  }
}

if (calendarEl && dayEventsContainer && dayEventsTitle && dayEventsList) {
  bootstrapCalendar();
}

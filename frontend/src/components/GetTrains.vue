<template>
  <div class="page">
    <h1>Welcome</h1>

      <label for="my-dropdown">Choose a train station:</label>
      <select id="my-dropdown" v-model="selected">
        <option v-for="opt in options" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>

      <div class="actions">
        <button @click="sendSelection" :disabled="!selected || sending">
          {{ sending ? 'Sending…' : 'Send to backend' }}
        </button>
      </div>

      <p>Selected: {{ selected }}</p>
      <p v-if="status" class="status">{{ status }}</p>
  </div>
</template>

<script setup lang="ts">
  import axios from 'axios';
  import { ref } from 'vue';

const options = [
  { value: '', label: 'Please select' },
  { value: 'A15', label: 'Shady Grove' },
  { value: 'A14', label: 'Rockville' },
  { value: 'A13', label: 'Twinbrook' },
  { value: 'A12', label: 'White Flint' },
  { value: 'A11', label: 'Grosvenor' },
  { value: 'A10', label: 'Medical Center' },
  { value: 'A09', label: 'Bethesda' },
  { value: 'A08', label: 'Friendship Heights' },
  { value: 'A07', label: 'Tenleytown' },
  { value: 'A06', label: 'Van Ness UDC' },
  { value: 'A05', label: 'Cleveland Park' },
  { value: 'A04', label: 'Woodley Park Zoo' },
  { value: 'A03', label: 'Dupont Circle' },
  { value: 'A02', label: 'Farragut North' },
  { value: 'A01', label: 'Metro Center' },
  { value: 'B01', label: 'Gallery Place' },
  { value: 'B02', label: 'Judiciary Square' },
  { value: 'B03', label: 'Union Station' },
  { value: 'B35', label: 'New York Avenue' },
  { value: 'B04', label: 'Rhode Island Avenue' },
  { value: 'B05', label: 'Brookland' },
  { value: 'B06', label: 'Fort Totten' },
  { value: 'B07', label: 'Takoma' },
  { value: 'B08', label: 'Silver Spring' },
  { value: 'B09', label: 'Forest Glen' },
  { value: 'B10', label: 'Wheaton' },
  { value: 'B11', label: 'Glenmont' },
]

const selected = ref('')
const sending = ref(false)
const status = ref('')

async function sendSelection() {
  if (!selected.value) return null
  sending.value = true
  status.value = ''
  try {
    const res = await axios.post(`http://localhost:8000/train-data/${selected.value}`, {
      stationCode: selected.value,
    })
    const data = res.data

    status.value = 'Sent successfully' + (data ? ` — ${typeof data === 'string' ? data : JSON.stringify(data)}` : '')
    return data
  } catch (err) {
    // axios error handling
    const e: any = err
    if (e.response) {
      status.value = `Error sending: HTTP ${e.response.status}`
    } else if (e.request) {
      status.value = 'Error sending: No response received'
    } else {
      status.value = `Error sending: ${e.message}`
    }
    return null
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
.page { padding: 1rem; max-width: 600px; }
label { display: block; margin-bottom: 0.5rem; }
select { padding: 0.5rem; border-radius: 4px; border: 1px solid #ccc; }
.actions { margin-top: 0.75rem; }
button { padding: 0.5rem 0.75rem; border-radius: 4px; border: 1px solid #888; background: #fff; cursor: pointer; }
.status { margin-top: 0.5rem; color: #444; }
</style>

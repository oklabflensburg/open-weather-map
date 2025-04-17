<template>
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Contact Us</h1>
    <p class="mb-6">
      We'd love to hear from you. Send us a message using the form below.
    </p>
    
    <form class="max-w-lg" @submit.prevent="submitForm">
      <div class="mb-4">
        <label for="name" class="block text-gray-700 mb-2">Name</label>
        <input 
          v-model="formData.name"
          type="text" 
          id="name" 
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Your name"
          required
        />
      </div>
      
      <div class="mb-4">
        <label for="email" class="block text-gray-700 mb-2">Email</label>
        <input 
          v-model="formData.email"
          type="email" 
          id="email" 
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="you@example.com"
          required
        />
      </div>
      
      <div class="mb-4">
        <label for="message" class="block text-gray-700 mb-2">Message</label>
        <textarea 
          v-model="formData.message"
          id="message" 
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          rows="5"
          placeholder="Your message"
          required
        ></textarea>
      </div>
      
      <div>
        <button 
          type="submit" 
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
          :disabled="isSubmitting"
        >
          {{ isSubmitting ? 'Sending...' : 'Send Message' }}
        </button>
      </div>
      
      <!-- Success message -->
      <div v-if="showSuccess" class="mt-4 p-3 bg-green-100 text-green-700 rounded-md">
        Thank you for your message! We'll get back to you soon.
      </div>
      
      <!-- Error message -->
      <div v-if="errorMessage" class="mt-4 p-3 bg-red-100 text-red-700 rounded-md">
        {{ errorMessage }}
      </div>
    </form>
    
    <div class="mt-6">
      <NuxtLink to="/" class="text-blue-600 hover:text-blue-800 underline">
        Return to Map
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
import { useCompanyInfo } from '~/composables/useCompanyInfo'

const company = useCompanyInfo()

// Form state
const formData = ref({
  name: '',
  email: '',
  message: ''
})

const isSubmitting = ref(false)
const showSuccess = ref(false)
const errorMessage = ref('')

// Form submission handler
const submitForm = async () => {
  isSubmitting.value = true
  errorMessage.value = ''
  showSuccess.value = false
  
  try {
    // Simulate API call (replace with actual API call later)
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    // Clear form and show success message
    formData.value = { name: '', email: '', message: '' }
    showSuccess.value = true
    isSubmitting.value = false
    
    // Hide success message after 5 seconds
    setTimeout(() => {
      showSuccess.value = false
    }, 5000)
  } catch (error) {
    isSubmitting.value = false
    errorMessage.value = 'Sorry, there was an error sending your message. Please try again.'
    console.error('Form submission error:', error)
  }
}
</script>

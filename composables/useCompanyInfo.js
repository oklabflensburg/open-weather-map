/**
 * Composable to access company information from runtime config
 */
export const useCompanyInfo = () => {
  const config = useRuntimeConfig()
  
  // Return company info without default fallbacks
  return {
    name: config.public.company?.name,
    street: config.public.company?.street,
    zipCode: config.public.company?.zipCode,
    city: config.public.company?.city,
    email: config.public.company?.email,
    phone: config.public.company?.phone,
    responsiblePerson: config.public.company?.responsiblePerson,
    domain: config.public.company?.domain,
    
    // Helper method to get full address
    getFullAddress() {
      return `${this.name}\n${this.street}\n${this.zipCode} ${this.city}`
    },
    
    // Helper method to get formatted address for HTML
    getFormattedAddress() {
      return `${this.name}<br>${this.street}<br>${this.zipCode} ${this.city}`
    }
  }
}
